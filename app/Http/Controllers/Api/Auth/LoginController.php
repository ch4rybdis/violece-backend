<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where(function ($query) use ($request) {
            $query->where('email', $request->login)
                ->orWhere('phone', $request->login);
        })
            ->where('is_active', true)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        // Check verification status
        if (!$user->email_verified_at && $user->email) {
            return response()->json([
                'message' => 'Please verify your email first.',
                'verification_required' => true,
                'verification_type' => 'email'
            ], 403);
        }

        // Update last active
        $user->update(['last_active_at' => now()]);

        // Revoke existing tokens for security (optional)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Login successful.',
            'data' => new AuthResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful.'
        ]);
    }

    public function me(): JsonResponse
    {
        return response()->json([
            'data' => new AuthResource(auth()->user())
        ]);
    }
}
