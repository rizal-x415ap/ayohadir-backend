<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the API health check endpoint returns 200 and healthy status.
     */
    public function test_api_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'app',
                    'version',
                    'database',
                    'timestamp',
                ],
                'meta' => [
                    'timestamp',
                ],
            ])
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.database', 'ONLINE');
    }
}
