<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\UserPhoneNumber;

class ProfileTest extends TestCase
{
    // Remove the setUp method entirely - let parent handle seeding
    /*protected function setUp(): void
    {
        parent::setUp();
        
        UserPhoneNumber::create(['users_phonenumber' => '08012345678']);
    }*/

    /** @test */
    public function user_can_view_profile()
    {
        $loginResponse = $this->postJson('/api/login', [
            'login' => '08012345678',
            'password' => '08012345678',
        ]);
        
        $token = $loginResponse->json('token');
        
        // Change password first (required)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/change-password', [
                'new_password' => 'newpass123',
                'new_password_confirmation' => 'newpass123',
                'email' => 'test@example.com',
            ]);
        
        // Get new token after password change
        $newLoginResponse = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'newpass123',
        ]);
        
        $newToken = $newLoginResponse->json('token');
        
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $newToken])
            ->getJson('/api/profile');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'phone',
                'email',
                'must_change_password',
                'account_status',
                'application_status' => [
                    'has_application',
                    'is_complete',
                    'reference_number',
                    'submission_date',
                    'last_updated'
                ]
            ]);
    }

    /** @test */
    public function cannot_view_profile_before_changing_password()
    {
        $loginResponse = $this->postJson('/api/login', [
            'login' => '08012345678',
            'password' => '08012345678',
        ]);
        
        $token = $loginResponse->json('token');
        
        // Try to view profile without changing password
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/profile');
        
        $response->assertStatus(423)
            ->assertJson(['message' => 'Change password first']);
    }
}