#!/usr/bin/env bash
#
# Manager.ShopEra — ayri deploy: SaaS kontrol paneli (Laravel + Blade/htmx admin).
#
# Kullanim:
#   bash deploy/deploy.sh                    # mevcut checkout uzerinde deploy
#   PULL=1 bash deploy/deploy.sh             # once origin/main'den git pull
#   PULL=1 sudo -E bash deploy/deploy.sh     # + izinleri duzelt (root)
#   SKIP_ASSETS=1 bash deploy/deploy.sh      # admin asset build'ini atla
#
# GitHub push'ta otomatik: .github/workflows/deploy.yml (SSH ile bu script'i cagirir).
#
set -euo pipefail

# ── Ayarlar (env ile ezilebilir) ─────────────────────────────────────────────
APP_DIR="${APP_DIR:-/var/www/Manager.Shopera}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
NPM_BIN="${NPM_BIN:-npm}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

PULL="${PULL:-0}"
SKIP_ASSETS="${SKIP_ASSETS:-0}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"
RUN_SEED="${RUN_SEED:-0}"                # 1 => db:seed --force (dikkatli kullan)

PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
QUEUE_PROGRAM="${QUEUE_PROGRAM:-}"       # supervisor program adi; bos => atlanir

# ── Yardimcilar ──────────────────────────────────────────────────────────────
info() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m  ✓\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m  !!\033[0m %s\n' "$*" >&2; }

has() { command -v "$1" >/dev/null 2>&1; }

unit_exists() {
    has systemctl && systemctl list-unit-files 2>/dev/null | grep -q "^$1\.service"
}

reload_php_fpm() {
    if unit_exists "$PHP_FPM_SERVICE"; then
        info "PHP-FPM yenileniyor: $PHP_FPM_SERVICE"
        systemctl reload "$PHP_FPM_SERVICE" 2>/dev/null || systemctl restart "$PHP_FPM_SERVICE"
        ok "php-fpm"
    else
        warn "systemd unit yok: $PHP_FPM_SERVICE (atlandi)"
    fi
}

restart_queue() {
    [ -n "$QUEUE_PROGRAM" ] || return 0
    if has supervisorctl; then
        info "Kuyruk worker yenileniyor: $QUEUE_PROGRAM"
        supervisorctl restart "$QUEUE_PROGRAM" >/dev/null 2>&1 && ok "queue" \
            || warn "supervisor program bulunamadi: $QUEUE_PROGRAM (atlandi)"
    else
        warn "supervisorctl yok (queue atlandi)"
    fi
}

# ── Baslangic ────────────────────────────────────────────────────────────────
[ -d "$APP_DIR" ] || { warn "APP_DIR bulunamadi: $APP_DIR"; exit 1; }
cd "$APP_DIR"

echo "──────────────────────────────────────────────"
echo " Manager.ShopEra deploy  •  $(date '+%Y-%m-%d %H:%M:%S')"
echo " dizin : $APP_DIR"
echo " branch: $BRANCH"
echo "──────────────────────────────────────────────"

# 1) Kod guncellemesi
if [ "$PULL" = "1" ]; then
    info "Git guncelleniyor (origin/$BRANCH)"
    git fetch --prune origin
    git checkout "$BRANCH" 2>/dev/null || git checkout -B "$BRANCH" "origin/$BRANCH"
    git reset --hard "origin/$BRANCH"
    ok "commit: $(git rev-parse --short HEAD)"
fi

# 2) PHP bagimliliklari
info "Composer bagimliliklari"
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
ok "vendor"

# 3) Admin panel assetleri (root Vite: resources/admin/app.ts)
if [ "$SKIP_ASSETS" != "1" ]; then
    info "Admin assetleri (npm ci + vite build)"
    "$NPM_BIN" ci --no-audit --no-fund
    "$NPM_BIN" run build
    ok "public/build"
else
    warn "asset build atlandi (SKIP_ASSETS=1)"
fi

# 4) Veritabani
if [ "$RUN_MIGRATIONS" = "1" ]; then
    info "Migration"
    "$PHP_BIN" artisan migrate --force
    ok "migrate"
fi

if [ "$RUN_SEED" = "1" ]; then
    info "Seeder"
    "$PHP_BIN" artisan db:seed --force
    ok "seed"
fi

# 5) Cache
info "Cache yenile (optimize:clear + optimize)"
"$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true
"$PHP_BIN" artisan optimize
ok "config/route/view cache"

# 6) Izinler (yalnizca root iken)
if [ "$(id -u)" -eq 0 ] && [ -f deploy/scripts/fix-permissions.sh ]; then
    info "Izinler duzeltiliyor"
    APP_DIR="$APP_DIR" APP_USER="$APP_USER" APP_GROUP="$APP_GROUP" \
        bash deploy/scripts/fix-permissions.sh
    ok "permissions"
fi

# 7) Servisler
restart_queue
reload_php_fpm

echo "──────────────────────────────────────────────"
ok "Deploy tamamlandi → commit $(git rev-parse --short HEAD 2>/dev/null || echo '?')"
echo "──────────────────────────────────────────────"
