<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /** @test */
    public function health_check_returns_ok_status()
    {
        $response = $this->getJson('/api/health');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'environment',
                'database'
            ]);
    }

    /** @test */
    public function detailed_health_check_returns_all_components()
    {
        $response = $this->getJson('/api/health/detailed');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'timestamp',
                'environment',
                'checks' => [
                    'database',
                    'mail',
                    'storage',
                    'queue'
                ]
            ]);
    }
}