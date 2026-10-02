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
        return $this->dispatchResult($owner, $event, $extra)['ok'];
    }

    /**
     * @return array{ok:bool,url?:string,status?:int,message:string,body?:string}
     */
    public function dispatchResult(SiteOwner $owner, string $event = 'entitlements.updated', array $extra = []): array
    {
        if (empty($owner->instance_url) || empty($owner->webhook_secret)) {
            return ['ok' => false, 'message' => 'Instance URL or webhook secret is missing.'];
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
        $url = $this->webhookUrl($owner);
        $hostHeader = $this->webhookHost($owner, $primary);

        try {
            $request = Http::timeout($timeout)
                ->retry($event === 'tenant.provision' ? 2 : 1, 500)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Manager-Event' => $event,
                    'X-Manager-Signature' => $signature,
                ])
                ->when($hostHeader !== '', fn ($http) => $http->withHeader('Host', $hostHeader))
                ->withBody($body, 'application/json');

            $response = $request->post($url);

            $result = [
                'ok' => $response->successful(),
                'url' => $url,
                'status' => $response->status(),
                'message' => $response->successful() ? 'Webhook delivered.' : 'Webhook returned an error.',
                'body' => substr((string) $response->body(), 0, 1000),
            ];

            if (! $result['ok']) {
                Log::warning('Manager webhook returned an error', [
                    'owner' => $owner->id,
                    'event' => $event,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $result['body'],
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Manager webhook failed', [
                'owner' => $owner->id,
                'event' => $event,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'url' => $url,
                'message' => $e->getMessage(),
            ];
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
        return $this->provisionResult($owner, $extra)['ok'];
    }

    /**
     * @return array{ok:bool,url?:string,status?:int,message:string,body?:string}
     */
    public function provisionResult(SiteOwner $owner, array $extra = []): array
    {
        $owner->loadMissing('domains');

        return $this->dispatchResult($owner, 'tenant.provision', array_merge([
            'hosts' => $owner->domains->pluck('host')->all(),
            'tenant_slug' => $owner->tenantSlug(),
            'database' => $owner->db_name,
            'storage_root' => $owner->storageRoot(),
        ], $extra));
    }

    private function webhookUrl(SiteOwner $owner): string
    {
        $url = (string) config('manager.webhook_url');

        if ($url === '') {
            $url = rtrim($owner->instance_url, '/').'/api/manager/webhook';
        }

        return $url;
    }

    private function webhookHost(SiteOwner $owner, ?string $primary): string
    {
        $configured = (string) config('manager.webhook_host');

        if ($configured !== '') {
            return $configured;
        }

        $host = parse_url($owner->instance_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : (string) $primary;
    }
}
