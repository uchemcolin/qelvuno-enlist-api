<?php

namespace Tests\Feature\Application;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AddressesTest extends TestCase
{
    /**
     * Helper method to get an authenticated token for testing
     * 
     * This method handles the complete authentication flow:
     * 1. Login with default phone number
     * 2. Change password (required for first-time users)
     * 3. Login again with new credentials
     * 4. Return the new token
     * 
     * @return string The authentication token
     */
    private function getAuthenticatedToken()
    {
        // Step 1: Login with default credentials (phone number as password)
        $loginResponse = $this->postJson('/api/login', [
            'login' => '08012345678',
            'password' => '08012345678',
        ]);
        
        $token = $loginResponse->json('token');
        
        // Step 2: First-time users must change their password and set email
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/change-password', [
                'new_password' => 'newpass123',
                'new_password_confirmation' => 'newpass123',
                'email' => 'test@example.com',
            ]);
        
        // Step 3: Re-login with new credentials (email as login)
        $newLoginResponse = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'newpass123',
        ]);
        
        // Step 4: Return the new token for subsequent authenticated requests
        return $newLoginResponse->json('token');
    }

    /**
     * Test that a user can submit addresses after completing personal info
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Submit personal info (Step 1)
     * 3. Submit addresses (Step 2)
     * 4. Verify successful response with correct message
     */
    #[Test]
    public function user_can_submit_addresses_after_personal_info()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // Step 1: Submit personal information first (required before addresses)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/personal-info', $this->getValidPersonalInfoData());
        
        // Step 2: Submit addresses (permanent and residential)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/addresses', $this->getValidAddressesData());
        
        // Assert: Verify the response
        $response->assertStatus(200)
            ->assertJson(['message' => 'Step 2 completed: Addresses saved successfully']);
    }

    /**
     * Test that addresses cannot be submitted without personal info first
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Attempt to submit addresses without personal info
     * 3. Verify proper error response
     */
    #[Test]
    public function cannot_submit_addresses_without_personal_info_first()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // Attempt to submit addresses without first completing personal info
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/addresses', $this->getValidAddressesData());
        
        // Assert: Should return 400 Bad Request with specific message
        $response->assertStatus(400)
            ->assertJson(['message' => 'Complete personal info first (Step 1)']);
    }

    /**
     * Test that address validation works correctly
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Submit personal info
     * 3. Submit addresses with missing required fields
     * 4. Verify validation errors
     */
    #[Test]
    public function addresses_validation_works()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // First submit personal info (required for addresses)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/personal-info', $this->getValidPersonalInfoData());
        
        // Submit addresses with only one field (missing many required fields)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/addresses', [
                'perm_street' => '123 Main St' // Missing other required fields
            ]);
        
        // Assert: Should return 422 Unprocessable Entity with validation errors
        $response->assertStatus(422);
    }
}