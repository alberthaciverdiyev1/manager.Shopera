<?php

namespace Tests\Feature;

use App\Services\CloudflareDns;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDnsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'cloudflare.enabled' => true,
            'cloudflare.api_token' => 'test-token',
            'cloudflare.zone_id' => null,
            'cloudflare.base_domain' => 'shopera.test',
            'cloudflare.target' => '203.0.113.10',
            'cloudflare.target_type' => 'A',
            'cloudflare.proxied' => true,
            'cloudflare.wildcard_subdomains' => true,
        ]);
    }

    public function test_generated_subdomain_is_covered_by_the_wildcard(): void
    {
        Http::fake();

        $this->assertTrue(app(CloudflareDns::class)->ensureDomain('redbull.shopera.test'));
        Http::assertNothingSent();
    }

    public function test_custom_domain_creates_a_proxied_record(): void
    {
        $this->fakeCloudflare();

        $this->assertTrue(app(CloudflareDns::class)->ensureDomain('shop.mystore.com'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/zones/zone-mystore/dns_records')
            && ($request->data()['name'] ?? null) === 'shop.mystore.com'
            && ($request->data()['proxied'] ?? null) === true
            && ($request->data()['content'] ?? null) === '203.0.113.10');
    }

    public function test_existing_record_is_not_recreated(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/zones?')) {
                return Http::response(['success' => true, 'result' => [['id' => 'zone-mystore', 'name' => 'mystore.com']]]);
            }

            return Http::response(['success' => true, 'result' => [['id' => 'rec-existing']]]);
        });

        $this->assertTrue(app(CloudflareDns::class)->ensureDomain('shop.mystore.com'));
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_disabled_service_does_nothing(): void
    {
        config(['cloudflare.enabled' => false]);
        Http::fake();

        $this->assertFalse(app(CloudflareDns::class)->ensureDomain('shop.mystore.com'));
        Http::assertNothingSent();
    }

    public function test_unknown_zone_is_reported_without_creating(): void
    {
        Http::fake(function () {
            return Http::response(['success' => true, 'result' => [['id' => 'zone-other', 'name' => 'other.com']]]);
        });

        $this->assertFalse(app(CloudflareDns::class)->ensureDomain('shop.mystore.com'));
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    private function fakeCloudflare(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/zones?')) {
                return Http::response(['success' => true, 'result' => [
                    ['id' => 'zone-mystore', 'name' => 'mystore.com'],
                    ['id' => 'zone-shopera', 'name' => 'shopera.test'],
                ]]);
            }

            if (str_contains($url, 'dns_records')) {
                if ($request->method() === 'GET') {
                    return Http::response(['success' => true, 'result' => []]);
                }

                return Http::response(['success' => true, 'result' => ['id' => 'rec-new']]);
            }

            return Http::response(['success' => true, 'result' => []]);
        });
    }
}
