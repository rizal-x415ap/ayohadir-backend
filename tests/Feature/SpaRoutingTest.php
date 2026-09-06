<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaRoutingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that root and web routes redirect browser visitors to the frontend SPA.
     */
    public function test_web_routes_redirect_to_frontend(): void
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        $response = $this->get('/');
        $response->assertStatus(302)
            ->assertRedirect($frontendUrl);

        $loginResponse = $this->get('/login');
        $loginResponse->assertStatus(302)
            ->assertRedirect($frontendUrl);
    }

    /**
     * Test that OG meta route redirects or serves meta preview for invitation slugs.
     */
    public function test_og_route_handles_invitation(): void
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        // Non-existent wedding slug redirects directly to frontend
        $response = $this->get('/og/unknown-slug');
        $response->assertStatus(302)
            ->assertRedirect($frontendUrl . '/unknown-slug');
    }
}
