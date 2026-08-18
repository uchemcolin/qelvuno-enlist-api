<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class PasswordChangeTest extends TestCase
{
    /** @test */
    public function first_time_user_must_set_email_when_changing_password()
    {
        $loginResponse = $this->postJson('/api/login', [
            'login' => '08012345678',
            'password' => '08012345678',
        ]);
        
        $token = $loginResponse->json('token');
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/change-password', [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
            'email' => 'user@example.com',
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Password set successfully. Email saved.'
            ]);
        
        $user = User::where('phone', '08012345678')->first();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertEquals('user@example.com', $user->email);
        
        // After password change, must_change_password should be 0 (false)
        $this->assertEquals(0, $user->must_change_password);
    }

    /** @test */
    public function existing_user_must_provide_current_password_to_change()
    {
        $user = User::create([
            'phone' => '08012345678',
            'email' => 'existing@example.com',
            'password' => Hash::make('currentpass123'),
            'must_change_password' => false,
        ]);
        
        $token = $user->createToken('auth')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/change-password', [
            'current_password' => 'currentpass123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ]);
        
        $response->assertStatus(200)
            ->assertJson(['status' => 'success']);
        
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword456', $user->password));
    }

    /** @test */
    public function password_change_fails_with_incorrect_current_password()
    {
        $user = User::create([
            'phone' => '08012345678',
            'email' => 'existing@example.com',
            'password' => Hash::make('correctpass123'),
            'must_change_password' => false,
        ]);
        
        $token = $user->createToken('auth')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/change-password', [
            'current_password' => 'wrongpass123',
            'new_password' => 'newpassword456',
            'new_password_confirmation' => 'newpassword456',
        ]);
        
        $response->assertStatus(401)
            ->assertJson(['message' => 'Current password is incorrect']);
    }
}