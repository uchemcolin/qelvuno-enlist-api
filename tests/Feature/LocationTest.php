<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocationTest extends TestCase
{
    /** @test */
    public function can_get_all_states()
    {
        $response = $this->getJson('/api/states');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['State_id', 'StateName', 'StateCode']
                ],
                'total'
            ]);
    }

    /** @test */
    public function can_get_lgas_by_state_code()
    {
        $response = $this->getJson('/api/lgas/NG-LA');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'state',
                'data' => [
                    '*' => ['loclGov_id', 'LocalGovName', 'LocalGovCode']
                ],
                'total'
            ]);
    }

    /** @test */
    public function returns_404_for_invalid_state()
    {
        $response = $this->getJson('/api/lgas/INVALID-STATE');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'State not found with code or ID: INVALID-STATE'
            ]);
    }
}