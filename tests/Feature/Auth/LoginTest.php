<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    //use RefreshDatabase;

    /** @test */
    /*public function user_can_login_with_enlisted_phone_number()
    {
        // Make sure phone is enlisted (already seeded in TestCase)
        
        $response = $this->postJson('/api/login', [
            'login' => '08012345678',
            'password' => '08012345678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'token',
                'user' => ['phone']
            ]);
    }*/

    public function user_can_login_with_enlisted_phone_number()
    {
        $response = $this->postJson('/api/login', [
            'login' => '08012345678',
            'password' => '08012345678',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'token',
                'user' => ['phone']
            ]);
    }

    /** @test */
    public function user_cannot_login_with_non_enlisted_phone()
    {
        $response = $this->postJson('/api/login', [
            'login' => '08999999999',
            'password' => '08999999999',
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Phone number not registered']);
    }

    /** @test */
    public function user_can_login_with_email_after_password_set()
    {
        // Create a user with completed setup using the helper
        $user = $this->createAuthenticatedUser('08012345678', 'test@example.com', true);
        
        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'token',
                'user' => ['id', 'phone', 'email']
            ])
            ->assertJson(['status' => 'success']);
    }

    /** @test */
    public function login_fails_with_invalid_credentials()
    {
        // Create a user first
        $this->createAuthenticatedUser('08012345678', 'test@example.com', true);
        
        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }
}