<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = User::create([
                'uuid' => Str::uuid(),
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'preference_gender' => $request->preference_gender,
                'location' => $this->createLocationPoint($request->latitude, $request->longitude),
                'location_updated_at' => ($request->latitude !== null && $request->longitude !== null) ? now() : null,
                'last_active_at' => now(),
                'is_active' => true,
            ]);

            // Send verification (SMS or Email)
            if ($request->phone) {
                $this->authService->sendSMSVerification($user);
            }

            if ($request->email) {
                $this->authService->sendEmailVerification($user);
            }

            DB::commit();

            return response()->json([
                'message' => 'Registration successful! Please verify your account.',
                'data' => new AuthResource($user),
                'verification_required' => true,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Registration failed.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    private function createLocationPoint(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return DB::raw("ST_GeomFromText('POINT({$longitude} {$latitude})', 4326)");
    }

}
