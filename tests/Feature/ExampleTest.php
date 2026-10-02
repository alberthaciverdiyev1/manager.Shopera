<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The Manager root redirects into the admin panel.
     */
    public function test_the_application_redirects_to_the_admin_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('admin.dashboard'));
    }
}
