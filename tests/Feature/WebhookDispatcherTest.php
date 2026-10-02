<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\SiteOwner;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDispatcherTest extends TestCase
{
    public function test_entitlement_events_carry_the_tenant_host(): void
    {
        Http::fake();

        $owner = new SiteOwner([
            'id' => 1,
            'instance_url' => 'https://shopera.test',
            'webhook_secret' => 'secret',
        ]);
        $owner->setRelation('domains', collect([
            new Domain(['host' => 'store.shopera.test', 'is_primary' => true]),
            new Domain(['host' => 'store.example.com', 'is_primary' => false]),
        ]));

        $ok = (new WebhookDispatcher)->dispatch($owner, 'entitlements.updated');

        $this->assertTrue($ok);

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return $payload['event'] === 'entitlements.updated'
                && $payload['host'] === 'store.shopera.test'
                && $payload['hosts'] === ['store.shopera.test', 'store.example.com'];
        });
    }

    public function test_missing_instance_url_is_not_sent(): void
    {
        Http::fake();

        $owner = new SiteOwner(['id' => 2, 'webhook_secret' => 'secret']);
        $owner->setRelation('domains', collect());

        $this->assertFalse((new WebhookDispatcher)->dispatch($owner, 'entitlements.updated'));
        Http::assertNothingSent();
    }
}
