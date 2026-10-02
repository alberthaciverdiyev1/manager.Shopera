# CLAUDE.md — Manager.Shopera

Bu fayl, Claude Code-un **Manager.Shopera** layihəsində işləyərkən uyması
gereken qaydaları və layihə kontekstini təyin edir. Talimatlar Türkçe yazılıb;
kod, dəyişən və commit içerikleri İngilizce konvansiyonunu koruyur.

## Layihə Özeti

**Manager.Shopera** — ShopEra vitrin/mağaza instansiyalarını (site sahiblerini)
abonelik modeli ile idarə eden **ayrıca SaaS kontrol paneli**. ShopEra
backend'inden tamamile ayrıdır: **ayrı veritabanı**, **ayrı REST API**,
ayrı deploy.

- Site sahipleri **domain** ile idarə olunur (məs. `redbull.shopera.test`).
- Her sahibin: abonelik planı, abonelik durumu, **hansı özellikleri yapıp
  yapamayacağı** (feature entitlement), kendi tema rengleri, limitleri olur.
- Manager bu qeydleri saxlayır ve ShopEra instansiyalarına **entitlement/theme**
  melumatını verir (pull/push).

- PHP `^8.3`, Laravel `^12`
- Veritabanı: PostgreSQL (ayrı baza: `manager_shopera`)
- Frontend build: Vite + Tailwind (+ Flowbite), Blade + htmx + TypeScript
- API: REST, `routes/api.php` (token ile qorunur)

## Sık Kullanılan Komutlar

```bash
composer install
php artisan migrate
php artisan migrate:fresh --seed
php artisan serve            # http://localhost:8010
npm install && npm run dev   # frontend
npm run build
php artisan test
```

## Dizin Yapısı (hedef)

```
app/
├── Http/Controllers/Admin/   # Blade+htmx idarə paneli
├── Http/Controllers/Api/     # REST API
├── Models/                   # SiteOwner, Domain, Plan, Subscription, Feature, Theme...
├── Services/                 # iş mantığı (Controller → Service → Model)
database/migrations/          # şema
resources/
├── views/admin/              # Blade panel (CLAUDE qaydaları ShopEra ile eynidir)
├── admin/                    # app.ts + app.css (Vite girişi)
routes/web.php · routes/api.php
```

## Domen Modeli (esas cədvəllər)

- `site_owners` — müşteri/site sahibi (ad, e-posta, telefon, status)
- `domains` — sahibə bağlı domen(lər), `host` unikal (redbull.shopera.test)
- `plans` — abonelik planları (ad, qiymət, limitlər)
- `plan_features` — planın verdiyi feature'lar
- `subscriptions` — sahib ↔ plan, başlangıç/bitiş, status
- `features` — feature kataloqu (key, ad, tip: bool/limit)
- `owner_features` — sahibə xas override (planı üstələyir)
- `themes` — default + sahibə xas tema rəngleri
- `settings` — global parametrler

## Kod Konvansiyonları

- **Controller → Service → Model** axını mecburidir. Controller içinde iş
  mantığı yazma.
- Doğrulama `Http/Requests/*Request.php` içinde.
- API cavabları vahid zərflə: `{success, status_code, message, data}`.
- Blade panel ShopEra admin paneli ile eyni üslubda: **Blade + htmx + Tailwind
  + Flowbite**, TypeScript `resources/admin/app.ts`.
- Enum-lar `app/Enums/`.
- PSR-12, 4 boşluq, LF, UTF-8.
- Sırları (`.env`, API tokenleri) commit etme ve log-lara yazma.

## Feature / Entitlement mantığı

- Feature açarı `key` ile tanınır (məs. `banners`, `chat`, `max_products`).
- Effektiv feature = `owner_features` override > `plan_features` > default(false/limit).
- `FeatureService::enabled($siteOwner, $key)` ve `limit($siteOwner, $key)`.
- ShopEra instansiyası buna göre özəlliyi açıb-bağlayır.

## Claude İçin Qaydalar

1. **Önce oku, sonra yaz.** Mövcud model/servis/route-ları nəzərə al.
2. **Kapsamı dar tut.** Lazımsız refactor etmə.
3. **API sözleşmesini qoru** — ShopEra instansiyaları bu API-ni oxuyacaq.
4. **Sırları ifşa etmə.**
5. Yeni feature/limit əlavə edəndə `features` seed + `FeatureService` ilə uyğun saxla.

## Domenlər və Cloudflare

Bütün domenlər Cloudflare arxasındadır; TLS Cloudflare edge-də bitir, origin düz HTTP qala bilər.
ShopEra tərəfi tək `server_name _` bloku ilə istənilən `Host`-u qarşılayır və `ResolveTenant`
tenant bazasını seçir — yəni domen əlavə etmək üçün nginx/vhost dəyişikliyi lazım deyil.

- **Subdomenlər avtomatik:** `*.base_domain` wildcard DNS qeydi ilə örtülür
  (`CLOUDFLARE_WILDCARD_SUBDOMAINS=true`). `OwnerController` yeni sahib üçün
  `<slug>.<base_domain>` generasiya edir; ayrıca DNS qeydi yaradılmır.
- **Custom domain:** istifadəçi domeni əvvəlcə Cloudflare hesabına (zone kimi) əlavə edir,
  sonra Manager-də sahibin `domains` sahəsinə yazır. `OwnerController::syncDns` →
  `App\Services\CloudflareDns::ensureDomain()` uyğun zone-u tapıb **proxied** A/CNAME
  qeydini avtomatik yaradır. Domain çıxarılanda qeyd silinir.
- Zone siyahısı 1 saat cache-lənir; yeni əlavə olunmuş zone tapılmasa cache təzələnib
  bir dəfə yenidən yoxlanılır.

Əlaqəli env-lər: `CLOUDFLARE_ENABLED`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID`
(base domen üçün opsional), `CLOUDFLARE_BASE_DOMAIN`, `CLOUDFLARE_DNS_TARGET`
(IP və ya tunnel hostname), `CLOUDFLARE_DNS_TYPE` (A/CNAME), `CLOUDFLARE_PROXIED`,
`CLOUDFLARE_WILDCARD_SUBDOMAINS`.
