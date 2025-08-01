<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Auth\UserResource;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show authenticated user's profile
     */
    public function show(): JsonResponse
    {
        $user = Auth::user()->load('psychologicalProfile');

        return response()->json([
            'status' => 'success',
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Update user profile
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:50',
            'bio' => 'sometimes|nullable|string|max:500',
            'interests' => 'sometimes|nullable|array',
            'interests.*' => 'string|max:30',
            'gender' => ['sometimes', 'string', Rule::in(['male', 'female', 'non-binary', 'other'])],
            'date_of_birth' => 'sometimes|date|before:-18 years',
            'profile_photos' => 'sometimes|array',
            'profile_photos.*.id' => 'required|string',
            'profile_photos.*.url' => 'required|url',
            'profile_photos.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $userData = $request->only([
                'first_name', 'bio', 'interests', 'gender', 'date_of_birth'
            ]);

            if ($request->has('profile_photos')) {
                $userData['profile_photos'] = $request->input('profile_photos');
            }

            $user->update($userData);

            // Calculate profile completion
            $this->updateProfileCompletionScore($user);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => new UserResource($user)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update profile',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Add a photo to user profile
     */
    public function addPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo_id' => 'required|string',
            'photo_url' => 'required|url',
            'order' => 'sometimes|integer|min:0',
        ]);

        try {
            $user = Auth::user();

            // Get current photos or initialize empty array
            $currentPhotos = $user->profile_photos ?? [];

            // Check if we're not exceeding the limit
            if (count($currentPhotos) >= 6) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Maximum number of photos (6) reached'
                ], 422);
            }

            // Add the new photo
            $newPhoto = [
                'id' => $request->input('photo_id'),
                'url' => $request->input('photo_url'),
                'order' => $request->input('order', count($currentPhotos)),
                'added_at' => now()->toISOString()
            ];

            $currentPhotos[] = $newPhoto;

            // Update user photos
            $user->update([
                'profile_photos' => $currentPhotos,
                'profile_completion_score' => $this->calculateProfileScore($user, true)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Photo added successfully',
                'data' => [
                    'photo' => $newPhoto,
                    'profile_photos' => $user->profile_photos,
                    'profile_completion_score' => $user->profile_completion_score
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add photo',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove a photo from user profile
     */
    public function removePhoto(string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get current photos
            $currentPhotos = $user->profile_photos ?? [];

            // Find the photo by ID
            $filteredPhotos = array_filter($currentPhotos, function ($photo) use ($id) {
                return $photo['id'] !== $id;
            });

            // If no photo was removed, return error
            if (count($filteredPhotos) === count($currentPhotos)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Photo not found'
                ], 404);
            }

            // Re-index array
            $filteredPhotos = array_values($filteredPhotos);

            // Update user photos
            $user->update([
                'profile_photos' => $filteredPhotos,
                'profile_completion_score' => $this->calculateProfileScore($user, false)
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Photo removed successfully',
                'data' => [
                    'profile_photos' => $user->profile_photos,
                    'profile_completion_score' => $user->profile_completion_score
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove photo',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update dating preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preference_gender' => ['sometimes', 'string', Rule::in(['male', 'female', 'both', 'all'])],
            'age_min' => 'sometimes|integer|min:18|max:100',
            'age_max' => 'sometimes|integer|min:18|max:100|gte:age_min',
            'distance_max' => 'sometimes|integer|min:1|max:500',
            'show_me_in_discovery' => 'sometimes|boolean',
            'match_filters' => 'sometimes|array',
            'match_filters.interests' => 'sometimes|array',
            'match_filters.interests.*' => 'string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            $preferencesData = $request->only([
                'preference_gender', 'age_min', 'age_max', 'distance_max', 'show_me_in_discovery', 'match_filters'
            ]);

            $user->update($preferencesData);

            return response()->json([
                'status' => 'success',
                'message' => 'Preferences updated successfully',
                'data' => [
                    'preferences' => [
                        'preference_gender' => $user->preference_gender,
                        'age_min' => $user->age_min,
                        'age_max' => $user->age_max,
                        'distance_max' => $user->distance_max,
                        'show_me_in_discovery' => $user->show_me_in_discovery,
                        'match_filters' => $user->match_filters
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update preferences',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update user location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'city' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Update location
            $user->update([
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'city' => $request->input('city'),
                'country' => $request->input('country'),
                'location' => DB::raw("ST_MakePoint({$request->input('longitude')}, {$request->input('latitude')})")
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Location updated successfully',
                'data' => [
                    'location' => [
                        'latitude' => $user->latitude,
                        'longitude' => $user->longitude,
                        'city' => $user->city,
                        'country' => $user->country
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update location',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate profile completion score
     */
    private function calculateProfileScore(User $user, bool $photoAdded = false): int
    {
        $score = 0;

        // Base profile information
        if (!empty($user->first_name)) $score += 5;
        if (!empty($user->date_of_birth)) $score += 10;
        if (!empty($user->gender)) $score += 10;
        if (!empty($user->bio)) $score += 15;
        if (!empty($user->interests) && count($user->interests) >= 3) $score += 15;

        // Photos (up to 25 points)
        $photoCount = count($user->profile_photos ?? []);
        if ($photoAdded) $photoCount++; // If a photo is being added
        $score += min(25, $photoCount * 5);

        // Preferences
        if (!empty($user->preference_gender)) $score += 5;
        if (!empty($user->age_min) && !empty($user->age_max)) $score += 5;
        if (!empty($user->distance_max)) $score += 5;

        // Psychological profile
        $psychProfile = $user->psychologicalProfile;
        if ($psychProfile && $psychProfile->is_complete) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Update profile completion score
     */
    private function updateProfileCompletionScore(User $user): void
    {
        $score = $this->calculateProfileScore($user);
        $user->update(['profile_completion_score' => $score]);
    }
}
