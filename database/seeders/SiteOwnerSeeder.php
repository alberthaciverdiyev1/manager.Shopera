<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Plan;
use App\Models\SiteOwner;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class SiteOwnerSeeder extends Seeder
{
    public function run(): void
    {
        $pro = Plan::query()->where('slug', 'pro')->first();

        $owner = SiteOwner::query()->updateOrCreate(
            ['email' => 'owner@redbull.test'],
            [
                'name' => 'Red Bull',
                'company' => 'Red Bull AZ',
                'phone' => '0500000002',
                'status' => 'active',
                'api_token' => 'redbull-dev-token-1234567890',
                'instance_url' => 'http://shopera-web',
                'webhook_secret' => 'redbull-webhook-secret-1234',
                'tenant_slug' => 'redbull',
                'db_name' => 'shopera_redbull',
            ]
        );

        Domain::query()->updateOrCreate(
            ['host' => 'redbull.shopera.test'],
            ['site_owner_id' => $owner->id, 'is_primary' => true, 'is_verified' => true, 'ssl_enabled' => true]
        );

        if ($pro) {
            Subscription::query()->updateOrCreate(
                ['site_owner_id' => $owner->id, 'plan_id' => $pro->id],
                ['status' => 'active', 'price' => $pro->price, 'starts_at' => now(), 'ends_at' => now()->addYear()]
            );
        }
    }
}
