<?php

namespace Tests\Feature\Application;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EducationTest extends TestCase
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
     * Test that a user can submit education without masters degree
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Submit personal info
     * 3. Submit education without masters details
     * 4. Verify successful response
     */
    #[Test]
    public function user_can_submit_education_without_masters()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // Step 1: Submit personal info first (required)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/personal-info', $this->getValidPersonalInfoData());
        
        // Step 2: Submit education information
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/education-work-experience', $this->getValidEducationData());
        
        // Assert: Verify successful response
        $response->assertStatus(200)
            ->assertJson(['message' => 'Step 4 completed: Education and Work Experience saved successfully']);
    }

    /**
     * Test that a user can submit education with full masters details
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Submit personal info
     * 3. Submit education with complete masters details
     * 4. Verify successful response
     */
    #[Test]
    public function user_can_submit_education_with_full_masters_details()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // Step 1: Submit personal info first (required)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/personal-info', $this->getValidPersonalInfoData());
        
        // Step 2: Prepare education data with masters details
        $educationData = $this->getValidEducationData();
        $educationData['masters_institution_name'] = 'University of Masters';
        $educationData['masters_address'] = 'Masters Address';
        $educationData['masters_state'] = 1;
        $educationData['masters_discipline'] = 'Advanced Computing';
        $educationData['masters_matric_no'] = 'MSc/2020/123';
        $educationData['masters_graduation_year'] = 2022;
        
        // Step 3: Submit education information
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/education-work-experience', $educationData);
        
        // Assert: Verify successful response
        $response->assertStatus(200)
            ->assertJson(['message' => 'Step 4 completed: Education and Work Experience saved successfully']);
    }

    /**
     * Test that partial masters submission fails validation
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Submit personal info
     * 3. Submit education with partial masters details (missing required fields)
     * 4. Verify validation errors
     */
    #[Test]
    public function partial_masters_submission_fails_validation()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // Step 1: Submit personal info first (required)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/personal-info', $this->getValidPersonalInfoData());
        
        // Step 2: Prepare education data with incomplete masters details
        $educationData = $this->getValidEducationData();
        $educationData['masters_institution_name'] = 'University of Masters';
        // Missing: masters_address, masters_state, masters_discipline, etc.
        
        // Step 3: Submit education information
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/education-work-experience', $educationData);
        
        // Assert: Should return 422 Unprocessable Entity
        $response->assertStatus(422);
        
        // Assert: All masters fields are validated when any is present
        $response->assertJsonValidationErrors([
            'masters_discipline',
            'masters_matric_no',
            'masters_graduation_year'
        ]);
    }

    /**
     * Test that education validation requires all basic fields
     * 
     * Flow:
     * 1. Authenticate user
     * 2. Submit personal info
     * 3. Submit education with only one field
     * 4. Verify all required validation errors
     */
    #[Test]
    public function education_validation_requires_all_basic_fields()
    {
        // Get authentication token for test user
        $token = $this->getAuthenticatedToken();
        
        // Step 1: Submit personal info first (required)
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/personal-info', $this->getValidPersonalInfoData());
        
        // Step 2: Submit education with only one field
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/application/education-work-experience', [
                'primary_school' => 'Some School' // Missing all other required fields
            ]);
        
        // Assert: Should return 422 Unprocessable Entity
        $response->assertStatus(422);
        
        // Assert: All required fields have validation errors
        $response->assertJsonValidationErrors([
            'primary_address',
            'primary_state',
            'primary_end_date',
            'secondary_school',
            'secondary_address',
            'secondary_state',
            'secondary_end_date',
            'uni_name',
            'uni_address',
            'uni_state',
            'uni_discipline',
            'uni_degree',
            'uni_class',
            'uni_matric',
            'uni_graduation_year'
        ]);
    }
}