<?php

namespace App\Http\Controllers\Api\Matching;

use App\Http\Controllers\Controller;
use App\Services\Matching\CompatibilityScoringService;
use App\Models\User;
use App\Models\Dating\UserInteraction;  // ✅ ADDED
use App\Models\Dating\UserMatch;        // ✅ ADDED
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;   // ✅ ADDED

class MatchingController extends Controller
{
    protected CompatibilityScoringService $compatibilityService;

    public function __construct(CompatibilityScoringService $compatibilityService)
    {
        $this->middleware('auth:sanctum');
        $this->compatibilityService = $compatibilityService;
    }

    /**
     * Get potential matches for the authenticated user
     */


    /**
     * Add these missing methods to your MatchingController.php
     * Add them after the superLikeUser method
     */

    /**
     * Get user's matches
     */
    public function getUserMatches(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = min(50, $request->input('limit', 20));
            $page = $request->input('page', 1);

            // Since we don't have UserMatch model implemented yet,
            // let's return based on mutual interactions
            $mutualInteractions = UserInteraction::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('is_mutual', true);
            })
                ->orWhere(function ($query) use ($user) {
                    $query->where('target_user_id', $user->id)
                        ->where('is_mutual', true);
                })
                ->with(['user', 'targetUser'])
                ->orderBy('updated_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            $matchesData = $mutualInteractions->getCollection()->map(function ($interaction) use ($user) {
                // Get the other user in the interaction
                $otherUser = $interaction->user_id === $user->id ?
                    $interaction->targetUser : $interaction->user;

                return [
                    'match_id' => $interaction->id, // Temporary - should be actual match_id
                    'user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->first_name,
                        'age' => $otherUser->age(),
                        'photos' => $otherUser->profile_photos ?? [],
                        'bio' => $otherUser->bio,
                        'online_status' => $this->isUserOnline($otherUser)
                    ],
                    'compatibility_score' => 75.0, // Placeholder - calculate real score
                    'match_quality' => 'Good',
                    'matched_at' => $interaction->created_at,
                    'last_activity' => $interaction->updated_at,
                    'last_message' => null, // TODO: Implement when Message model is ready
                    'unread_count' => 0,
                    'conversation_starters' => [
                        "What's been the highlight of your week so far?",
                        "I'm curious - what's something you're passionate about lately?",
                        "What's your go-to way to unwind after a long day?"
                    ]
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'matches' => $matchesData,
                    'pagination' => [
                        'current_page' => $mutualInteractions->currentPage(),
                        'total_pages' => $mutualInteractions->lastPage(),
                        'total_matches' => $mutualInteractions->total(),
                        'per_page' => $mutualInteractions->perPage()
                    ],
                    'stats' => [
                        'total_active_matches' => $mutualInteractions->total(),
                        'unread_matches' => 0 // TODO: Calculate from messages
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to fetch user matches", [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch matches',
                'error_code' => 'MATCHES_FETCH_FAILED'
            ], 500);
        }
    }

    /**
     * Get user's interaction statistics
     */
    public function getUserStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Get interaction statistics
            $totalInteractions = UserInteraction::where('user_id', $user->id)->count();
            $likesGiven = UserInteraction::where('user_id', $user->id)
                ->where('interaction_type', UserInteraction::TYPE_LIKE)
                ->count();
            $superLikesGiven = UserInteraction::where('user_id', $user->id)
                ->where('interaction_type', UserInteraction::TYPE_SUPER_LIKE)
                ->count();
            $passesGiven = UserInteraction::where('user_id', $user->id)
                ->where('interaction_type', UserInteraction::TYPE_PASS)
                ->count();

            // Get mutual likes (matches)
            $mutualLikes = UserInteraction::where('user_id', $user->id)
                ->where('is_mutual', true)
                ->whereIn('interaction_type', [
                    UserInteraction::TYPE_LIKE,
                    UserInteraction::TYPE_SUPER_LIKE
                ])
                ->count();

            // Calculate success rate
            $likeSuccessRate = $likesGiven > 0 ? round(($mutualLikes / $likesGiven) * 100, 2) : 0;

            // Get daily limits
            $todayLikes = UserInteraction::where('user_id', $user->id)
                ->where('interaction_type', UserInteraction::TYPE_LIKE)
                ->whereDate('created_at', today())
                ->count();

            $todaySuperLikes = UserInteraction::where('user_id', $user->id)
                ->where('interaction_type', UserInteraction::TYPE_SUPER_LIKE)
                ->whereDate('created_at', today())
                ->count();

            // Set limits based on premium status
            $maxLikes = $user->is_premium ? 999 : 20;
            $maxSuperLikes = $user->is_premium ? 10 : 1;

            // Get super likes received
            $superLikesReceived = UserInteraction::where('target_user_id', $user->id)
                ->where('interaction_type', UserInteraction::TYPE_SUPER_LIKE)
                ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'interaction_stats' => [
                        'total_interactions' => $totalInteractions,
                        'likes_given' => $likesGiven,
                        'super_likes_given' => $superLikesGiven,
                        'passes_given' => $passesGiven,
                        'mutual_likes' => $mutualLikes,
                        'like_success_rate' => $likeSuccessRate
                    ],
                    'daily_limits' => [
                        'limits' => [
                            'likes' => $maxLikes,
                            'super_likes' => $maxSuperLikes,
                            'passes' => 999
                        ],
                        'used' => [
                            'likes' => $todayLikes,
                            'super_likes' => $todaySuperLikes,
                            'passes' => 0 // Passes are unlimited
                        ],
                        'remaining' => [
                            'likes' => max(0, $maxLikes - $todayLikes),
                            'super_likes' => max(0, $maxSuperLikes - $todaySuperLikes),
                            'passes' => 999
                        ]
                    ],
                    'match_stats' => [
                        'total_matches' => $mutualLikes,
                        'active_matches' => $mutualLikes, // TODO: Filter by active when UserMatch model is ready
                        'average_compatibility' => 72.5, // Placeholder
                        'best_compatibility' => 85.0 // Placeholder
                    ],
                    'profile_views' => 0, // TODO: Implement profile view tracking
                    'super_likes_received' => $superLikesReceived
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to fetch user stats", [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user stats',
                'error_code' => 'STATS_FETCH_FAILED'
            ], 500);
        }
    }


    public function getPotentialMatches(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = min(20, $request->input('limit', 10));
            $maxDistance = $user->max_distance ?? 50; // km

            // Get users already interacted with (to exclude)
            $excludedUserIds = UserInteraction::where('user_id', $user->id)
                ->pluck('target_user_id')
                ->push($user->id); // Exclude self
            $preferredGenders = collect(
                is_array($user->preference_gender)
                    ? $user->preference_gender
                    : json_decode($user->preference_gender, true)
            )->filter()->values()->toArray();

            if (empty($preferredGenders)) {
                $preferredGenders = [1, 2, 3]; // Varsayılan değer
            }


            // Build the potential matches query
            $potentialMatchesQuery = User::with(['psychologicalProfile'])
                ->whereNotIn('id', $excludedUserIds)
                ->where('is_active', true) // ✅ FIXED: direct field name
                ->whereHas('psychologicalProfile', function($query) {
                    $query->where('is_active', true);
                })
                ->whereIn('gender', $preferredGenders)
                ->when($user->preference_age_min && $user->preference_age_max, function($query) use ($user) {
                    $query->whereRaw('EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN ? AND ?',
                        [$user->preference_age_min, $user->preference_age_max]);
                });

            // Add location filtering if user has location
            if ($user->location) {
                $location = DB::select("SELECT ST_X(location) as lng, ST_Y(location) as lat FROM users WHERE id = ?", [$user->id])[0];

                $potentialMatchesQuery->whereRaw(
                    "ST_DWithin(location, ST_SetSRID(ST_MakePoint(?, ?), 4326), ?)",
                    [$location->lng, $location->lat, $maxDistance * 1000]
                );
            }

            // Get more users than needed for better filtering and ranking
            $potentialMatches = $potentialMatchesQuery
                ->limit($limit * 5)
                ->get();

            $scoredMatches = [];
            $processedCount = 0;

            foreach ($potentialMatches as $potentialMatch) {
                // Skip if processing too many (performance limit)
                if ($processedCount >= 100) break;

                try {
                    // Calculate compatibility score
                    $compatibility = $this->compatibilityService->calculateCompatibilityScore($user, $potentialMatch);

                    // Filter out low compatibility matches (below 35%)
                    if ($compatibility['total_score'] < 35) {
                        $processedCount++;
                        continue;
                    }

                    // Calculate distance if both users have location
                    $distance = null;
                    if ($user->location && $potentialMatch->location) {
                        $distance = $this->calculateDistance($user, $potentialMatch);
                    }

                    // Build match data
                    $matchData = [
                        'user' => [
                            'id' => $potentialMatch->id,
                            'name' => $potentialMatch->first_name,
                            'age' => $potentialMatch->age(),
                            'photos' => $potentialMatch->profile_photos ?? [],
                            'bio' => $potentialMatch->bio,
                            'location' => $potentialMatch->location ? 'Available' : null,
                            'distance_km' => $distance,
                            'last_seen' => $potentialMatch->last_active_at?->diffForHumans(), // ✅ FIXED field name
                            'is_online' => $this->isUserOnline($potentialMatch), // ✅ FIXED method
                            'profile_completion' => $this->getProfileCompletionPercentage($potentialMatch) // ✅ FIXED method
                        ],
                        'compatibility' => $compatibility,
                        'match_reasons' => $this->generateMatchReasons($compatibility),
                        'match_preview' => $this->generateMatchPreview($compatibility, $potentialMatch)
                    ];

                    $scoredMatches[] = $matchData;
                    $processedCount++;

                } catch (\Exception $e) {
                    // Log compatibility calculation error but continue
                    Log::warning("Compatibility calculation failed for users {$user->id} and {$potentialMatch->id}", [
                        'error' => $e->getMessage()
                    ]);
                    $processedCount++;
                    continue;
                }
            }

            // Sort by compatibility score (highest first)
            usort($scoredMatches, function($a, $b) {
                return $b['compatibility']['total_score'] <=> $a['compatibility']['total_score'];
            });

            // Apply final limit
            $finalMatches = array_slice($scoredMatches, 0, $limit);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'matches' => $finalMatches,
                    'meta' => [
                        'total_processed' => count($scoredMatches),
                        'total_found' => count($finalMatches),
                        'algorithm_version' => '1.2.0',
                        'search_radius_km' => $maxDistance
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get potential matches for user {$user->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to find potential matches',
                'error_code' => 'MATCHING_ERROR'
            ], 500);
        }
    }

    /**
     * Like a user (swipe right)
     */
    public function likeUser(Request $request, int $userId): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Validate target user exists and is not the current user
            if ($userId === $currentUser->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot like yourself',
                    'error_code' => 'INVALID_TARGET_USER'
                ], 400);
            }

            $targetUser = User::findOrFail($userId);

            // Check if interaction already exists
            $existingInteraction = UserInteraction::where('user_id', $currentUser->id)
                ->where('target_user_id', $userId)
                ->first();

            if ($existingInteraction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already interacted with this user',
                    'error_code' => 'INTERACTION_EXISTS',
                    'data' => ['existing_type' => $existingInteraction->interaction_type]
                ], 409);
            }

            // Check daily limits
            if (!$this->canUserLike($currentUser)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Daily like limit reached',
                    'error_code' => 'DAILY_LIMIT_EXCEEDED'
                ], 429);
            }

            DB::beginTransaction();

            try {
                // Record the like interaction (using existing integer constants)
                $interaction = UserInteraction::create([
                    'user_id' => $currentUser->id,
                    'target_user_id' => $userId,
                    'interaction_type' => UserInteraction::TYPE_LIKE, // ✅ FIXED: using existing constants
                    'interaction_context' => [
                        'timestamp' => now()->toISOString(),
                        'source' => 'discovery_queue'
                    ]
                ]);

                // Check for mutual like
                $mutualLike = UserInteraction::where('user_id', $userId)
                    ->where('target_user_id', $currentUser->id)
                    ->where('interaction_type', UserInteraction::TYPE_LIKE)
                    ->first();

                $matched = false;
                $matchData = null;

                if ($mutualLike) {
                    // Create match
                    $compatibility = $this->compatibilityService->calculateCompatibilityScore($currentUser, $targetUser);

                    $match = UserMatch::create([
                        'uuid' => \Illuminate\Support\Str::uuid(),
                        'user1_id' => min($currentUser->id, $userId),
                        'user2_id' => max($currentUser->id, $userId),
                        'match_score' => $compatibility['total_score'],
                        'algorithm_version' => '1.0',
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);


                    $matched = true;
                    $matchData = [
                        'match_id' => $match->id,
                        'compatibility_score' => $compatibility['total_score'],
                        'match_quality' => $this->getMatchQualityLabel($compatibility['total_score'])
                    ];
                }

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'liked' => true,
                        'matched' => $matched,
                        'match_data' => $matchData
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Failed to like user", [
                'user_id' => $currentUser->id ?? null,
                'target_user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to like user',
                'error_code' => 'LIKE_FAILED'
            ], 500);
        }
    }

    /**
     * Pass on a user (swipe left)
     */
    public function passUser(Request $request, int $userId): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Validate target user
            if ($userId === $currentUser->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot pass on yourself',
                    'error_code' => 'INVALID_TARGET_USER'
                ], 400);
            }

            User::findOrFail($userId);

            // Check if interaction already exists
            $existingInteraction = UserInteraction::where('user_id', $currentUser->id)
                ->where('target_user_id', $userId)
                ->first();

            if ($existingInteraction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already interacted with this user',
                    'error_code' => 'INTERACTION_EXISTS'
                ], 409);
            }

            // Record the pass interaction
            UserInteraction::create([
                'user_id' => $currentUser->id,
                'target_user_id' => $userId,
                'interaction_type' => UserInteraction::TYPE_PASS, // ✅ FIXED: using existing constants
                'interaction_context' => [
                    'timestamp' => now()->toISOString(),
                    'source' => 'discovery_queue'
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'passed' => true,
                    'message' => 'User passed successfully'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to pass user',
                'error_code' => 'PASS_FAILED'
            ], 500);
        }
    }

    /**
     * Super like a user (premium feature)
     */
    public function superLikeUser(Request $request, int $userId): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Check premium status
            if (!$currentUser->is_premium) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Super likes require premium subscription',
                    'error_code' => 'PREMIUM_REQUIRED'
                ], 403);
            }

            // Validate target user
            if ($userId === $currentUser->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot super like yourself',
                    'error_code' => 'INVALID_TARGET_USER'
                ], 400);
            }

            $targetUser = User::findOrFail($userId);

            // Check if interaction already exists
            $existingInteraction = UserInteraction::where('user_id', $currentUser->id)
                ->where('target_user_id', $userId)
                ->first();

            if ($existingInteraction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already interacted with this user',
                    'error_code' => 'INTERACTION_EXISTS'
                ], 409);
            }

            // Record the super like interaction
            UserInteraction::create([
                'user_id' => $currentUser->id,
                'target_user_id' => $userId,
                'interaction_type' => UserInteraction::TYPE_SUPER_LIKE, // ✅ FIXED: using existing constants
                'interaction_context' => [
                    'timestamp' => now()->toISOString(),
                    'source' => 'discovery_queue',
                    'premium_feature' => true
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'super_liked' => true,
                    'notification_sent' => true
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to super like user',
                'error_code' => 'SUPER_LIKE_FAILED'
            ], 500);
        }
    }

    /**
     * Get detailed compatibility analysis between two users
     */
    public function getCompatibilityAnalysis(Request $request, int $userId): JsonResponse
    {
        try {
            $currentUser = Auth::user();
            $targetUser = User::with('psychologicalProfile')->findOrFail($userId);

            $compatibility = $this->compatibilityService->calculateCompatibilityScore($currentUser, $targetUser);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'users' => [
                        'current_user' => $currentUser->only(['id', 'first_name']),
                        'target_user' => $targetUser->only(['id', 'first_name'])
                    ],
                    'compatibility_analysis' => $compatibility
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to analyze compatibility',
                'error_code' => 'COMPATIBILITY_ANALYSIS_ERROR'
            ], 500);
        }
    }

    // ✅ HELPER METHODS

    private function calculateDistance(User $user1, User $user2): ?float
    {
        try {
            if (!$user1->location || !$user2->location) {
                return null;
            }

            $result = DB::select("
            SELECT ST_Distance(
                ST_Transform(ST_GeomFromText('POINT({$user1->location->getLng()} {$user1->location->getLat()})',4326), 3857),
                ST_Transform(ST_GeomFromText('POINT({$user2->location->getLng()} {$user2->location->getLat()})',4326), 3857)
            ) / 1000 as distance_km
        ");

            return $result ? round($result[0]->distance_km, 1) : null;

        } catch (\Exception $e) {
            Log::warning("Distance calculation failed", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function generateMatchReasons(array $compatibility): array
    {
        $reasons = [];
        $score = $compatibility['total_score'];
        $components = $compatibility['component_scores'] ?? [];

        if ($score >= 80) {
            $reasons[] = "Exceptional psychological compatibility";
        } elseif ($score >= 70) {
            $reasons[] = "Strong personality alignment";
        }

        if (($components['attachment_compatibility'] ?? 0) >= 80) {
            $reasons[] = "Compatible attachment styles";
        }

        if (($components['personality_similarity'] ?? 0) >= 75) {
            $reasons[] = "Similar values and life approach";
        }

        if (empty($reasons)) {
            $reasons[] = "Potential for meaningful connection";
        }

        return array_slice($reasons, 0, 3);
    }

    private function generateMatchPreview(array $compatibility, User $user): array
    {
        return [
            'compatibility_level' => $this->getCompatibilityLevel($compatibility['total_score']),
            'top_connection' => 'Personality alignment',
            'potential_challenge' => null
        ];
    }

    private function getCompatibilityLevel(float $score): string
    {
        if ($score >= 85) return 'Exceptional';
        if ($score >= 75) return 'Excellent';
        if ($score >= 65) return 'Very Good';
        if ($score >= 55) return 'Good';
        if ($score >= 45) return 'Average';
        return 'Below Average';
    }

    private function getMatchQualityLabel(float $score): string
    {
        return $this->getCompatibilityLevel($score);
    }

    private function canUserLike(User $user): bool
    {
        if ($user->is_premium) return true;

        $todayLikes = UserInteraction::where('user_id', $user->id)
            ->where('interaction_type', UserInteraction::TYPE_LIKE)
            ->whereDate('created_at', today())
            ->count();

        return $todayLikes < 20; // Free tier limit
    }

    private function isUserOnline(User $user): bool
    {
        return $user->last_active_at &&
            $user->last_active_at->isAfter(now()->subMinutes(15));
    }

    private function getProfileCompletionPercentage(User $user): int
    {
        $fields = ['first_name', 'birth_date', 'gender', 'bio', 'location'];
        $completed = 0;

        foreach ($fields as $field) {
            if (!empty($user->$field)) {
                $completed++;
            }
        }

        return round(($completed / count($fields)) * 100);
    }




}
