<?php

namespace Tests\Feature;

use Tests\TestCase;

class OriginSecurityTest extends TestCase
{
    /**
     * Test that API requests with allowed Origin header are permitted.
     */
    public function test_allowed_origin_is_permitted(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
        ])->getJson('/api/v1/health');

        $response->assertStatus(200);
    }

    /**
     * Test that API requests with disallowed Origin header are rejected with 403 Forbidden.
     */
    public function test_disallowed_origin_is_blocked(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://malicious-website.com',
        ])->getJson('/api/v1/public/invitations/some-slug');

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Origin not allowed.',
                ],
            ]);
    }

    /**
     * Test that server-to-server or test requests without Origin header are permitted.
     */
    public function test_requests_without_origin_are_permitted(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);
    }
}
