<?php

namespace App\Services;

use App\Models\SiteOwner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pushes signed events to a ShopEra instance: a generic "refresh" and a
 * "tenant.provision" that creates/migrates the owner's own database.
 */
class WebhookDispatcher
{
    public function dispatch(SiteOwner $owner, string $event = 'entitlements.updated', array $extra = []): bool
    {
        if (empty($owner->instance_url) || empty($owner->webhook_secret)) {
            return false;
        }

        $owner->loadMissing('domains');
        $hosts = $owner->domains->pluck('host')->all();
        $primary = $owner->domains->firstWhere('is_primary', true)?->host ?? ($hosts[0] ?? null);

        // One instance serves many tenants, so every event must carry the host
        // it applies to — otherwise the instance cannot pick the right tenant.
        $payload = array_merge([
            'event' => $event,
            'owner_id' => $owner->id,
            'host' => $primary,
            'hosts' => $hosts,
            'sent_at' => now()->toIso8601String(),
        ], $extra);

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, $owner->webhook_secret);

        // Provisioning runs migrations, so give it time.
        $timeout = $event === 'tenant.provision' ? 180 : 6;

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Manager-Event' => $event,
                    'X-Manager-Signature' => $signature,
                ])
                ->withBody($body, 'application/json')
                ->post(rtrim($owner->instance_url, '/').'/api/manager/webhook');

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Manager webhook failed', ['owner' => $owner->id, 'event' => $event, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Notify every owner on a plan (e.g. after the plan's features change). */
    public function dispatchPlan(int $planId, string $event = 'plan.updated'): void
    {
        SiteOwner::query()
            ->whereHas('subscriptions', fn ($q) => $q->where('plan_id', $planId))
            ->get()
            ->each(fn (SiteOwner $owner) => $this->dispatch($owner, $event));
    }

    /** Ask the instance to create + migrate this owner's tenant database. */
    public function provision(SiteOwner $owner, array $extra = []): bool
    {
        $owner->loadMissing('domains');

        return $this->dispatch($owner, 'tenant.provision', array_merge([
            'hosts' => $owner->domains->pluck('host')->all(),
            'tenant_slug' => $owner->tenantSlug(),
            'database' => $owner->db_name,
            'storage_root' => $owner->storageRoot(),
        ], $extra));
    }
}
