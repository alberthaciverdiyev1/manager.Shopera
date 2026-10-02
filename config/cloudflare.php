<?php

return [
    // When enabled, adding/removing a domain on a site owner also creates or
    // deletes the matching DNS record in Cloudflare. TLS is terminated at the
    // Cloudflare edge, so the origin can stay plain HTTP.
    'enabled' => (bool) env('CLOUDFLARE_ENABLED', false),

    'api_token' => env('CLOUDFLARE_API_TOKEN'),

    // Optional pinned zone id for the platform's base domain.
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),

    // Base domain whose subdomains are generated automatically.
    'base_domain' => env('CLOUDFLARE_BASE_DOMAIN', env('MANAGER_BASE_DOMAIN', 'shopera.test')),

    // Where hostnames point: an IP (A record) or a hostname such as a
    // Cloudflare Tunnel target (CNAME record).
    'target' => env('CLOUDFLARE_DNS_TARGET'),
    'target_type' => strtoupper((string) env('CLOUDFLARE_DNS_TYPE', 'A')),

    // Cloudflare proxied (orange cloud) — required for edge TLS.
    'proxied' => (bool) env('CLOUDFLARE_PROXIED', true),

    // When a *.base_domain record already exists, generated subdomains need no
    // per-tenant DNS record.
    'wildcard_subdomains' => (bool) env('CLOUDFLARE_WILDCARD_SUBDOMAINS', true),

    'api_base' => 'https://api.cloudflare.com/client/v4',
];
