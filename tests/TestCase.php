<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    // Remove RefreshDatabase trait - we'll manage database manually
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Force SQLite connection for testing
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        
        // Run migrations to create all tables
        Artisan::call('migrate:fresh', [
            '--seed' => false,
            '--env' => 'testing'
        ]);
        
        // Fake storage for all tests
        Storage::fake('public');
        
        // Create essential tables and seed data
        $this->setupTestDatabase();
    }

    protected function tearDown(): void
    {
        // Clean up
        Artisan::call('migrate:reset', ['--force' => true]);
        
        parent::tearDown();
    }

    protected function setupTestDatabase(): void
    {
        try {
            // Run the legacy tables migration
            Artisan::call('migrate', ['--force' => true]);
            
            // Seed states and LGAs
            DB::table('recruitment_state')->insertOrIgnore([
                ['State_id' => 1, 'StateName' => 'Lagos', 'StateCode' => 'NG-LA'],
                ['State_id' => 2, 'StateName' => 'Abuja Federal Capital Territory', 'StateCode' => 'NG-FC'],
                ['State_id' => 3, 'StateName' => 'Rivers', 'StateCode' => 'NG-RI'],
                ['State_id' => 4, 'StateName' => 'Kano', 'StateCode' => 'NG-KN'],
            ]);

            DB::table('recruitment_local_gov')->insertOrIgnore([
                ['loclGov_id' => 1, 'LocalGovName' => 'Ikeja', 'LocalGovCode' => 'NG-LA001'],
                ['loclGov_id' => 2, 'LocalGovName' => 'Surulere', 'LocalGovCode' => 'NG-LA002'],
                ['loclGov_id' => 3, 'LocalGovName' => 'Victoria Island', 'LocalGovCode' => 'NG-LA003'],
            ]);

            DB::table('user_phonenumber')->insertOrIgnore([
                ['users_phonenumber' => '08012345678'],
                ['users_phonenumber' => '08098765432'],
            ]);
        } catch (\Exception $e) {
            error_log('Setup error: ' . $e->getMessage());
        }
    }

    // This method creates a user WITHOUT going through the login endpoint
    protected function createAuthenticatedUser($phone = '08012345678', $email = null, $setPassword = false): \App\Models\User
    {
        $user = \App\Models\User::firstOrCreate(
            ['phone' => $phone],
            [
                'email' => $email,
                'password' => $setPassword ? bcrypt('password123') : null,
                'must_change_password' => !$setPassword,
            ]
        );
        
        if ($email && !$user->email) {
            $user->email = $email;
            $user->save();
        }
        
        if ($setPassword && !$user->password) {
            $user->password = bcrypt('password123');
            $user->must_change_password = false;
            $user->save();
        }
        
        return $user;
    }

    // Helper to login and get token (for tests that need to go through login flow)
    protected function loginAndGetToken($phone = '08012345678', $password = null)
    {
        $password = $password ?? $phone;
        
        $response = $this->postJson('/api/login', [
            'login' => $phone,
            'password' => $password,
        ]);
        
        return $response->json('token');
    }

    protected function createCompletedUser(): \App\Models\User
    {
        $user = $this->createAuthenticatedUser('08012345678', 'test@example.com', true);
        
        $existingPersonalInfo = \App\Models\PersonalInfo::where('phoneNo', '08012345678')->first();
        
        if (!$existingPersonalInfo) {
            $personalInfo = \App\Models\PersonalInfo::create([
                'firstName' => 'John',
                'surname' => 'Doe',
                'middleName' => 'James',
                'maidenName' => 'Smith',
                'title' => 'Mr',
                'dateOfBirth' => '1990-01-01',
                'placeOfBirth' => 'Lagos',
                'gender' => 'Male',
                'state_of_origin' => 'Lagos',
                'local_govt' => 'Ikeja',
                'nationality' => 'Nigerian',
                'nin' => '12345678901',
                'phoneNo' => '08012345678',
                'email' => 'test@example.com',
                'referenceNo' => 'REF-12345',
                'passportPhotograph' => 'passport.jpg',
                'birthCertificate' => 'birthcert.jpg',
                'disability_id' => 0,
            ]);
            
            $user->biodataID = $personalInfo->biodataID;
            $user->save();
        } else {
            $user->biodataID = $existingPersonalInfo->biodataID;
            $user->save();
        }
        
        return $user;
    }

    protected function getFakePassport(): UploadedFile
    {
        return UploadedFile::fake()->image('passport.jpg', 200, 200)->size(100);
    }

    protected function getFakeBirthCertificate(): UploadedFile
    {
        return UploadedFile::fake()->image('birthcert.jpg', 800, 600)->size(150);
    }

    protected function getValidPersonalInfoData($overrides = []): array
    {
        return array_merge([
            'title' => 'Mr',
            'surname' => 'Doe',
            'firstName' => 'John',
            'middleName' => 'James',
            'maidenName' => 'Smith',
            'dateOfBirth' => '1990-01-01',
            'placeOfBirth' => 'Lagos',
            'gender' => 'Male',
            //'maritalStatus' => 'Single',
            'email' => 'john.doe@example.com',
            'phoneNo' => '08012345678',
            'state_of_origin' => 'Lagos',
            'local_govt' => 'Ikeja',
            'nationality' => 'Nigerian',
            'disability_id' => 0,
            'nin' => '12345678901',
            'passportPhotograph' => $this->getFakePassport(),
            'birthCertificate' => $this->getFakeBirthCertificate(),

            // Although in the db it is using
            // current timestamps for the two columns below
            'dateCreated' => now()->toDateTimeString(),
            'updatedDate' => now()->toDateTimeString(),
        ], $overrides);
    }

    protected function getValidAddressesData($overrides = []): array
    {
        return array_merge([
            'perm_street' => '123 Main Street',
            'perm_house_no' => '12A',
            'perm_area' => 'Ikeja',
            'perm_city' => 'Lagos',
            'perm_state' => 'Lagos',
            'perm_country' => 'Nigeria',
            'perm_phone' => '08012345678',
            'perm_email' => 'permanent@example.com',
            'res_street' => '456 Park Avenue',
            'res_house_no' => '45B',
            'res_area' => 'Victoria Island',
            'res_city' => 'Lagos',
            'res_state' => 'Lagos',
            'res_country' => 'Nigeria',
            'res_phone' => '08098765432',
            'res_email' => 'residential@example.com',
        ], $overrides);
    }

    protected function getValidNextOfKinData($overrides = []): array
    {
        return array_merge([
            'fullName' => 'Jane Doe',
            'address' => '789 Family Street, Lagos',
            'relationship' => 'Sister',
            'phone' => '08011223344',
            'email' => 'jane.doe@example.com',
            'gender' => 'Female',
        ], $overrides);
    }

    protected function getValidEducationData($overrides = []): array
    {
        return array_merge([
            'primary_school' => 'Primary School Name',
            'primary_address' => '123 Primary St',
            'primary_state' => 'Lagos',
            'primary_end_date' => '2000-06-30',
            'secondary_school' => 'Secondary School Name',
            'secondary_address' => '456 Secondary Ave',
            'secondary_state' => 'Lagos',
            'secondary_end_date' => '2006-05-30',
            'uni_name' => 'University of Lagos',
            'uni_address' => 'University Road',
            'uni_state' => 'Lagos',
            'uni_discipline' => 'Computer Science',
            'uni_degree' => 'BSc',
            'uni_class' => 'Second Class Upper',
            'uni_matric' => 'UNILAG/12/3456',
            'uni_graduation_year' => 2016,
        ], $overrides);
    }

    protected function getValidNyscData($overrides = []): array
    {
        return array_merge([
            'nysc_cert_no' => 'NYSC/2020/12345',
            'nysc_completion_date' => '2021-06-30',
            'nysc_type' => 'Regular',
            'prof_qualification' => 'Professional Certification',
            'prof_organization' => 'Professional Body',
            'prof_membership_no' => 'MEM/2020/123',
            'prof_date' => '2020-01-15',
            'prof_class' => 'Associate',
        ], $overrides);
    }

    protected function getValidWorkExperienceData($overrides = []): array
    {
        return array_merge([
            'experience' => [
                [
                    'position' => 'Software Engineer',
                    'company' => 'Tech Corp',
                    'start_date' => '2020-01-01',
                    'end_date' => '2022-12-31',
                ],
                [
                    'position' => 'Junior Developer',
                    'company' => 'Startup Inc',
                    'start_date' => '2018-06-01',
                    'end_date' => '2019-12-31',
                ],
            ],
        ], $overrides);
    }
}