<?php

namespace App\Http\Controllers\Api;

use App\Models\PromoBlock;
use App\Models\SiteOwner;
use App\Models\Theme;
use App\Services\FeatureService;
use App\Services\ThemeService;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Read-only endpoint ShopEra instances call to learn what their owner is
 * allowed to do and which colours to render.
 */
class EntitlementController extends Controller
{
    public function __construct(
        private readonly FeatureService $features,
        private readonly ThemeService $theme,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    public function show(Request $request)
    {
        $owner = $this->ownerFromRequest($request);

        if (! $owner) {
            return $this->error('Site not found.', 404);
        }

        $subscription = $owner->currentSubscription;
        $features = $this->features->resolve($owner);

        return $this->ok([
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'status' => $owner->status->value,
            ],
            'domain' => $request->query('host'),
            'tenant' => [
                'slug' => $owner->tenantSlug(),
                'database' => $owner->db_name,
                'storage_root' => $owner->storageRoot(),
            ],
            'subscription' => [
                'status' => $subscription?->status?->value,
                'plan' => $subscription?->plan?->name,
                'price' => $subscription?->price,
                'ends_at' => $subscription?->ends_at?->toIso8601String(),
                'usable' => (bool) $subscription?->status?->isUsable(),
            ],
            'features' => $features,
            'theme' => $this->theme->forOwner($owner),
            'promo_blocks' => $this->promoBlocks($features),
        ]);
    }

    /** Themes the owner may choose from, plus the current selection. */
    public function themes(Request $request)
    {
        $owner = $this->ownerFromRequest($request);

        if (! $owner) {
            return $this->error('Site not found.', 404);
        }

        return $this->ok([
            'current' => $owner->theme_id,
            'themes' => $this->theme->themes()->map(fn ($theme) => [
                'id' => $theme->id,
                'name' => $theme->name,
                'slug' => $theme->slug,
                'description' => $theme->description,
                'is_default' => (bool) $theme->is_default,
                'preview' => $this->theme->paletteForTheme($theme),
            ])->all(),
        ]);
    }

    /** The owner's own admin selects a theme from the catalogue. */
    public function selectTheme(Request $request)
    {
        $owner = $this->ownerFromRequest($request);

        if (! $owner) {
            return $this->error('Site not found.', 404);
        }

        $data = $request->validate([
            'theme_id' => ['required', 'integer', 'exists:themes,id'],
        ]);

        $theme = Theme::query()->active()->find($data['theme_id']);

        if (! $theme) {
            return $this->error('Theme is not available.', 422);
        }

        $owner->update(['theme_id' => $theme->id]);
        $this->webhooks->dispatch($owner, 'theme.updated');

        return $this->ok(['theme' => $this->theme->forOwner($owner->fresh('theme'))]);
    }

    /** ShopEra instances push their current usage counters here. */
    public function usage(Request $request)
    {
        $owner = $this->ownerFromRequest($request);

        if (! $owner) {
            return $this->error('Site not found.', 404);
        }

        $data = $request->validate([
            'products' => ['nullable', 'integer', 'min:0'],
            'categories' => ['nullable', 'integer', 'min:0'],
            'staff' => ['nullable', 'integer', 'min:0'],
            'storage_gb' => ['nullable', 'numeric', 'min:0'],
        ]);

        $owner->update([
            'usage_products' => $data['products'] ?? $owner->usage_products,
            'usage_categories' => $data['categories'] ?? $owner->usage_categories,
            'usage_staff' => $data['staff'] ?? $owner->usage_staff,
            'usage_storage_gb' => $data['storage_gb'] ?? $owner->usage_storage_gb,
            'usage_reported_at' => now(),
        ]);

        return $this->ok(['reported_at' => $owner->usage_reported_at?->toIso8601String()]);
    }

    /** All host → database mappings (used by instances for tenant routing). */
    public function tenants()
    {
        $rows = SiteOwner::query()->with('domains')->get()->flatMap(function (SiteOwner $owner) {
            return $owner->domains->map(fn ($d) => [
                'host' => $d->host,
                'database' => $owner->db_name,
                'tenant_slug' => $owner->tenantSlug(),
                'storage_root' => $owner->storageRoot(),
                'instance_url' => $owner->instance_url,
                'status' => $owner->status->value,
            ]);
        })->filter(fn ($r) => $r['database'])->values();

        return $this->ok(['tenants' => $rows]);
    }

    public function theme(Request $request)
    {
        $owner = $this->ownerFromRequest($request);

        if (! $owner) {
            return $this->error('Site not found.', 404);
        }

        return $this->ok(['theme' => $this->theme->forOwner($owner)]);
    }

    /** Offer/ad blocks are a Free-plan perk; only sent when the feature is on. */
    private function promoBlocks(array $features): array
    {
        $enabled = in_array(strtolower((string) ($features['promo_blocks']['value'] ?? '')), ['1', 'true', 'yes', 'on'], true);

        if (! $enabled) {
            return [];
        }

        return PromoBlock::query()->active()->orderBy('sort_order')->get()->map(fn (PromoBlock $b) => [
            'id' => $b->id,
            'type' => $b->type,
            'title' => $b->title,
            'subtitle' => $b->subtitle,
            'description' => $b->description,
            'image' => $b->image,
            'button_text' => $b->button_text,
            'url' => $b->url,
            'badge' => $b->badge,
        ])->all();
    }

    private function ownerFromRequest(Request $request): ?SiteOwner
    {
        $host = (string) ($request->input('host') ?? $request->query('host'));

        // 1) ?host= is authoritative: one ShopEra instance serves many tenants,
        //    so a single instance token must not pin every lookup to its owner.
        if ($host !== '') {
            // An unknown host resolves to nothing (404) rather than silently
            // falling back to the calling token's owner.
            return SiteOwner::query()
                ->byHost($host)
                ->with(['domains', 'currentSubscription.plan.features', 'ownerFeatures.feature'])
                ->first();
        }

        // 2) No host: use the owner resolved from the bearer token.
        $fromToken = $request->attributes->get('site_owner');
        if ($fromToken instanceof SiteOwner) {
            return $fromToken->load(['domains', 'currentSubscription.plan.features', 'ownerFeatures.feature']);
        }

        return null;
    }

    private function ok(array $data)
    {
        return response()->json(['success' => true, 'status_code' => 200, 'message' => 'OK', 'data' => $data]);
    }

    private function error(string $message, int $code)
    {
        return response()->json(['success' => false, 'status_code' => $code, 'message' => $message, 'data' => null], $code);
    }
}
