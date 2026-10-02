<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates/deletes Cloudflare DNS records for tenant hostnames.
 *
 * - Generated subdomains of the base domain are covered by a wildcard record
 *   (CLOUDFLARE_WILDCARD_SUBDOMAINS=true) and need no per-tenant record.
 * - Custom domains: once the domain (zone) exists in the same Cloudflare
 *   account, a proxied record is created automatically, so the site goes live
 *   right after being added.
 *
 * TLS is handled by Cloudflare; the origin only has to answer for any Host.
 */
class CloudflareDns
{
    public function enabled(): bool
    {
        return (bool) config('cloudflare.enabled') && ! empty(config('cloudflare.api_token'));
    }

    /** Ensure a DNS record exists for the host. Returns true when it is (or is already) resolvable. */
    public function ensureDomain(string $host): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $host = $this->normalize($host);

        if ($host === '') {
            return false;
        }

        if ($this->isWildcardCovered($host)) {
            return true;
        }

        $zoneId = $this->zoneIdFor($host);

        if (! $zoneId) {
            // The zone may have been added to Cloudflare after it was cached.
            Cache::forget('cloudflare_zones');
            $zoneId = $this->zoneIdFor($host);
        }

        if (! $zoneId) {
            Log::warning('Cloudflare: no zone found for host.', ['host' => $host]);

            return false;
        }

        try {
            if ($this->findRecord($zoneId, $host)) {
                return true;
            }

            $response = $this->client()->post("/zones/{$zoneId}/dns_records", [
                'type' => (string) config('cloudflare.target_type'),
                'name' => $host,
                'content' => (string) config('cloudflare.target'),
                'proxied' => (bool) config('cloudflare.proxied'),
                'ttl' => 1,
            ]);

            if (! $response->successful() || ! $response->json('success')) {
                Log::warning('Cloudflare: DNS record creation failed.', [
                    'host' => $host,
                    'status' => $response->status(),
                    'errors' => $response->json('errors'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cloudflare: DNS record creation error.', ['host' => $host, 'error' => $e->getMessage()]);

            return false;
        }
    }

    public function removeDomain(string $host): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $host = $this->normalize($host);

        if ($host === '' || $this->isWildcardCovered($host)) {
            return true;
        }

        $zoneId = $this->zoneIdFor($host);

        if (! $zoneId) {
            return false;
        }

        try {
            foreach ($this->findRecords($zoneId, $host) as $record) {
                $this->client()->delete("/zones/{$zoneId}/dns_records/{$record['id']}");
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Cloudflare: DNS record removal error.', ['host' => $host, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** A generated subdomain of the base domain, already covered by the wildcard. */
    private function isWildcardCovered(string $host): bool
    {
        if (! config('cloudflare.wildcard_subdomains')) {
            return false;
        }

        $base = trim((string) config('cloudflare.base_domain'), '.');

        return $base !== '' && str_ends_with($host, '.'.$base);
    }

    private function zoneIdFor(string $host): ?string
    {
        $base = trim((string) config('cloudflare.base_domain'), '.');
        $pinned = (string) config('cloudflare.zone_id');

        if ($pinned !== '' && $base !== '' && ($host === $base || str_ends_with($host, '.'.$base))) {
            return $pinned;
        }

        // Longest matching zone suffix wins (e.g. shop.mystore.com → mystore.com).
        foreach ($this->zones() as $zone) {
            $name = (string) ($zone['name'] ?? '');

            if ($name !== '' && ($host === $name || str_ends_with($host, '.'.$name))) {
                return (string) ($zone['id'] ?? '');
            }
        }

        return null;
    }

    /** @return array<int,array{id:string,name:string}> */
    private function zones(): array
    {
        return Cache::remember('cloudflare_zones', 3600, function () {
            try {
                $response = $this->client()->get('/zones', ['per_page' => 50]);

                if (! $response->successful() || ! $response->json('success')) {
                    return [];
                }

                return array_map(fn ($z) => [
                    'id' => (string) ($z['id'] ?? ''),
                    'name' => strtolower((string) ($z['name'] ?? '')),
                ], $response->json('result') ?? []);
            } catch (\Throwable $e) {
                Log::warning('Cloudflare: zone listing failed.', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    private function findRecord(string $zoneId, string $host): ?array
    {
        return $this->findRecords($zoneId, $host)[0] ?? null;
    }

    /** @return array<int,array{id:string}> */
    private function findRecords(string $zoneId, string $host): array
    {
        $response = $this->client()->get("/zones/{$zoneId}/dns_records", ['name' => $host]);

        if (! $response->successful() || ! $response->json('success')) {
            return [];
        }

        return $response->json('result') ?? [];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('cloudflare.api_base'))
            ->withToken((string) config('cloudflare.api_token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 300);
    }

    private function normalize(string $host): string
    {
        return strtolower(trim(preg_replace('#^https?://#i', '', $host), " \t\n\r\0\x0B/."));
    }
}
