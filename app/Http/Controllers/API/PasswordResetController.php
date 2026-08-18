<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendPasswordResetJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgot(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        if (!$user->password) {
            //return response()->json(['message' => 'Activate account using phone first'], 400);
            return response()->json(['message' => 'Activate account using default password first'], 400);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        /*// Simple email sending
        $resetUrl = env('FRONTEND_URL', 'http://localhost:3000') . "/reset-password?token={$token}&email={$request->email}";
        
        Mail::raw("Click here to reset your password: {$resetUrl}", function ($message) use ($request) {
            $message->to($request->email)->subject('Reset Password');
        });*/

        // Frontend URL
        $frontendUrl = config('recruitment_urls.frontend');

        //$resetUrl = env('FRONTEND_URL', 'http://localhost:3000') . "/reset-password?token={$token}&email={$request->email}";

        $resetUrl = $frontendUrl . "/reset-password?token={$token}&email={$request->email}";

        // QUEUE the email instead of sending immediately
        //SendPasswordResetJob::dispatch($request->email, $token, $resetUrl, $name);
        SendPasswordResetJob::dispatch($request->email, $token, $resetUrl);

        return response()->json(['message' => 'Reset link sent']);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed'
        ]);

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful']);
    }
}