<?php

namespace App\Http\Controllers\Api\Dating;

use App\Http\Controllers\Controller;
use App\Models\Dating\DateSuggestion;
use App\Models\Dating\UserMatch;
use App\Services\Dating\ConversationAnalyzerService;
use App\Services\Dating\DateSuggestionService;
use App\Events\Dating\DateSuggested;
use App\Notifications\Dating\DateSuggestionNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DateSuggestionController extends Controller
{
    protected ConversationAnalyzerService $analyzerService;
    protected DateSuggestionService $suggestionService;

    public function __construct(
        ConversationAnalyzerService $analyzerService,
        DateSuggestionService $suggestionService
    ) {
        $this->middleware('auth:sanctum');
        $this->analyzerService = $analyzerService;
        $this->suggestionService = $suggestionService;
    }

    /**
     * Manually request a date suggestion
     */
    public function requestSuggestion(UserMatch $match): JsonResponse
    {
        // Check if user is part of the match
        if ($match->user1_id !== Auth::id() && $match->user2_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to access this match'
            ], 403);
        }

        // Check if recent suggestion already exists
        $recentSuggestion = $match->dateSuggestions()
            ->where('created_at', '>=', now()->subHours(6))
            ->first();

        if ($recentSuggestion) {
            return response()->json([
                'status' => 'success',
                'message' => 'Recent suggestion already exists',
                'data' => [
                    'suggestion' => $this->formatSuggestion($recentSuggestion)
                ]
            ]);
        }

        // Generate new suggestion
        $suggestion = $this->suggestionService->generateSuggestion($match);

        // Broadcast event
        broadcast(new DateSuggested($suggestion))->toOthers();

        // Notify other user
        $otherUser = $match->user1_id === Auth::id() ? $match->user2 : $match->user1;
        $otherUser->notify(new DateSuggestionNotification($suggestion));

        return response()->json([
            'status' => 'success',
            'message' => 'Date suggestion created',
            'data' => [
                'suggestion' => $this->formatSuggestion($suggestion)
            ]
        ]);
    }

    /**
     * Get date suggestions for a match
     */
    public function getSuggestions(UserMatch $match): JsonResponse
    {
        // Check if user is part of the match
        if ($match->user1_id !== Auth::id() && $match->user2_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to access this match'
            ], 403);
        }

        // Get all suggestions for this match
        $suggestions = $match->dateSuggestions()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($suggestion) {
                return $this->formatSuggestion($suggestion);
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'suggestions' => $suggestions
            ]
        ]);
    }

    /**
     * Respond to a date suggestion
     */
    public function respondToSuggestion(Request $request, DateSuggestion $suggestion): JsonResponse
    {
        $match = $suggestion->match;

        // Check if user is part of the match
        if ($match->user1_id !== Auth::id() && $match->user2_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to access this suggestion'
            ], 403);
        }

        // Validate response
        $request->validate([
            'response' => 'required|string|in:accept,reject'
        ]);

        // Update suggestion based on response
        // Update suggestion based on response
        if ($request->input('response') === 'accept') {
            $suggestion->accept();
            $message = 'Date suggestion accepted';
        } else {
            $suggestion->reject();
            $message = 'Date suggestion rejected';
        }

        // Get the other user in the match
        $otherUser = $match->user1_id === Auth::id() ? $match->user2 : $match->user1;

        // Notify the other user of the response
        // This would be a new notification type for responses
        // $otherUser->notify(new DateSuggestionResponseNotification($suggestion, Auth::user()));

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'suggestion' => $this->formatSuggestion($suggestion)
            ]
        ]);
    }

    /**
     * Trigger date suggestion analysis for a match
     * This would typically be called by a background job,
     * but we expose it as an endpoint for testing
     */
    public function analyzeSuggestionOpportunity(UserMatch $match): JsonResponse
    {
        // Check if user is part of the match
        if ($match->user1_id !== Auth::id() && $match->user2_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to access this match'
            ], 403);
        }

        // Check if suggestion should be made
        $shouldSuggest = $this->analyzerService->shouldSuggestDate($match);

        if (!$shouldSuggest) {
            return response()->json([
                'status' => 'success',
                'message' => 'Conditions not met for date suggestion',
                'data' => [
                    'should_suggest' => false,
                    'reasons' => $this->analyzerService->getReasons()
                ]
            ]);
        }

        // Generate suggestion
        $suggestion = $this->suggestionService->generateSuggestion($match);

        // Broadcast event
        broadcast(new DateSuggested($suggestion))->toOthers();

        // Notify both users
        $match->user1->notify(new DateSuggestionNotification($suggestion));
        $match->user2->notify(new DateSuggestionNotification($suggestion));

        return response()->json([
            'status' => 'success',
            'message' => 'Date suggestion created',
            'data' => [
                'should_suggest' => true,
                'suggestion' => $this->formatSuggestion($suggestion)
            ]
        ]);
    }

    /**
     * Format suggestion for API response
     */
    private function formatSuggestion(DateSuggestion $suggestion): array
    {
        return [
            'id' => $suggestion->id,
            'activity' => [
                'name' => $suggestion->activity_name,
                'type' => $suggestion->activity_type,
                'description' => $suggestion->activity_description,
            ],
            'venue' => [
                'name' => $suggestion->venue_name,
                'address' => $suggestion->venue_address,
                'location' => [
                    'latitude' => $suggestion->venue_latitude,
                    'longitude' => $suggestion->venue_longitude,
                ],
            ],
            'timing' => [
                'day' => $suggestion->day_name,
                'day_of_week' => $suggestion->suggested_day,
                'time' => $suggestion->formatted_time,
                'raw_time' => $suggestion->suggested_time,
            ],
            'compatibility_reason' => $suggestion->compatibility_reason,
            'status' => $suggestion->is_accepted ? 'accepted' : ($suggestion->is_rejected ? 'rejected' : 'pending'),
            'response_at' => $suggestion->response_at?->toISOString(),
            'created_at' => $suggestion->created_at->toISOString(),
        ];
    }
}
