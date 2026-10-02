<?php

return [
    // Shared secret that ShopEra instances send as X-Api-Key when pulling
    // their entitlements/theme from the Manager.
    'api_key' => env('MANAGER_API_KEY', 'change-me'),

    // Shared secret used to sign webhooks to instances (one codebase may
    // serve many tenants, so the instance verifies a single secret).
    'webhook_secret' => env('MANAGER_WEBHOOK_SECRET', 'dev-webhook-secret'),

    // Base domain used for auto-generated subdomains (redbull.shopera.test).
    'base_domain' => env('MANAGER_BASE_DOMAIN', 'shopera.test'),

    // Days before ends_at when a renewal reminder is sent.
    'reminder_days' => [7, 3, 1],

    // How many days after the due date reminders keep going out.
    'overdue_reminder_days' => 14,

    'billing_from' => env('MANAGER_BILLING_FROM', env('MAIL_FROM_ADDRESS', 'billing@shopera.az')),

    // Pre-filled Instance URL for new owners (ShopEra base URL).
    'default_instance_url' => env('MANAGER_DEFAULT_INSTANCE_URL'),

];
