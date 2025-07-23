<?php


// app/Http/Controllers/Api/Matching/MatchingController.php

namespace App\Http\Controllers\Api\Matching;

use App\Http\Controllers\Controller;
use App\Services\Matching\CompatibilityScoringService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    public function getPotentialMatches(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = min(20, $request->input('limit', 10));

            // Get users with psychological profiles
            $potentialMatches = User::with(['psychologicalProfile', 'photos'])
                ->where('id', '!=', $user->id)
                ->whereHas('psychologicalProfile', function($query) {
                    $query->where('is_active', true);
                })
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit($limit * 3) // Get more to filter and rank
                ->get();

            $scoredMatches = [];

            foreach ($potentialMatches as $potentialMatch) {
                $compatibility = $this->compatibilityService->calculateCompatibilityScore($user, $potentialMatch);

                if ($compatibility['total_score'] > 30) { // Minimum threshold
                    $scoredMatches[] = [
                        'user' => [
                            'id' => $potentialMatch->id,
                            'name' => $potentialMatch->name,
                            'age' => $potentialMatch->age,
                            'photos' => $potentialMatch->photos->take(3),
                            'bio' => $potentialMatch->bio,
                            'location' => $potentialMatch->location
                        ],
                        'compatibility' => $compatibility,
                        'match_reasons' => $this->generateMatchReasons($compatibility)
                    ];
                }
            }

            // Sort by compatibility score
            usort($scoredMatches, function($a, $b) {
                return $b['compatibility']['total_score'] <=> $a['compatibility']['total_score'];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'matches' => array_slice($scoredMatches, 0, $limit),
                    'total_found' => count($scoredMatches),
                    'algorithm_version' => '1.0.0'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to find potential matches',
                'error_code' => 'MATCHING_ERROR'
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
                        'current_user' => $currentUser->only(['id', 'name']),
                        'target_user' => $targetUser->only(['id', 'name'])
                    ],
                    'compatibility_analysis' => $compatibility,
                    'recommendations' => $this->generateRelationshipRecommendations($compatibility)
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

    private function generateMatchReasons(array $compatibility): array
    {
        $reasons = [];
        $score = $compatibility['total_score'];
        $components = $compatibility['component_scores'];

        if ($score >= 80) {
            $reasons[] = "Exceptional psychological compatibility";
        } elseif ($score >= 70) {
            $reasons[] = "Strong personality alignment";
        }

        if ($components['attachment_compatibility'] >= 80) {
            $reasons[] = "Compatible attachment styles";
        }

        if ($components['personality_similarity'] >= 75) {
            $reasons[] = "Similar values and life approach";
        }

        if ($components['complementarity_bonus'] >= 20) {
            $reasons[] = "Beneficial complementary traits";
        }

        if (empty($reasons)) {
            $reasons[] = "Potential for meaningful connection";
        }

        return array_slice($reasons, 0, 3);
    }

    private function generateRelationshipRecommendations(array $compatibility): array
    {
        $recommendations = [];
        $score = $compatibility['total_score'];

        if ($score >= 75) {
            $recommendations[] = "High compatibility - consider meeting in person soon";
        } elseif ($score >= 60) {
            $recommendations[] = "Good potential - spend time getting to know each other";
        } else {
            $recommendations[] = "Moderate match - focus on common interests";
        }

        // Add specific recommendations based on potential challenges
        if (!empty($compatibility['detailed_analysis']['potential_challenges'])) {
            $recommendations[] = "Be mindful of: " . $compatibility['detailed_analysis']['potential_challenges'][0];
        }

        return $recommendations;
    }
}
