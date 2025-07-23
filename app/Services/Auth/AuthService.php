<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function sendSMSVerification(User $user): void
    {
        $code = rand(100000, 999999);

        // Store verification code
        cache()->put("sms_verification_{$user->id}", $code, now()->addMinutes(10));

        // TODO: SMS service integration
        Log::info("SMS Verification code for {$user->phone}: {$code}");
    }

    public function sendEmailVerification(User $user): void
    {
        $token = \Illuminate\Support\Str::random(60);

        // Store verification token
        cache()->put("email_verification_{$user->id}", $token, now()->addHours(24));

        // TODO: Email service integration
        Log::info("Email verification token for {$user->email}: {$token}");
    }
}
