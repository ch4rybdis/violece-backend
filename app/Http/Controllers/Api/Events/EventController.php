<?php

namespace App\Http\Controllers\Api\Events;

use App\Http\Controllers\Controller;
use App\Http\Resources\Events\WeeklyEventResource;
use App\Http\Resources\Events\EventParticipationResource;
use App\Http\Resources\Events\EventMatchResource;
use App\Models\Events\WeeklyEvent;
use App\Models\Events\EventParticipation;
use App\Models\Events\EventQuestion;
use App\Models\Events\EventResponse;
use App\Models\Events\EventMatch;
use App\Services\Events\EventMatchmakingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    protected $matchmakingService;

    public function __construct(EventMatchmakingService $matchmakingService)
    {
        $this->middleware('auth:sanctum');
        $this->matchmakingService = $matchmakingService;
    }

    /**
     * Get active events
     */
    public function getActiveEvents(): JsonResponse
    {
        $events = WeeklyEvent::where(function ($query) {
            $query->where('status', WeeklyEvent::STATUS_SCHEDULED)
                ->orWhere('status', WeeklyEvent::STATUS_ACTIVE);
        })
            ->where('starts_at', '<=', now()->addDays(7))
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        // Check if user has joined each event
        $user = Auth::user();
        $events->each(function ($event) use ($user) {
            $event->has_joined = $event->participations()
                ->where('user_id', $user->id)
                ->exists();
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'events' => WeeklyEventResource::collection($events),
                'upcoming_count' => $events->count()
            ]
        ]);
    }

    /**
     * Get past events the user has participated in
     */
    public function getPastEvents(): JsonResponse
    {
        $user = Auth::user();

        $participations = EventParticipation::where('user_id', $user->id)
            ->whereHas('event', function ($query) {
                $query->where(function ($q) {
                    $q->where('status', WeeklyEvent::STATUS_COMPLETED)
                        ->orWhere('ends_at', '<', now());
                });
            })
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->get();

        $events = $participations->map(function ($participation) {
            $event = $participation->event;
            $event->participation_status = $participation->status;
            $event->completed_at = $participation->completed_at;
            return $event;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'events' => WeeklyEventResource::collection($events),
                'total_count' => $events->count()
            ]
        ]);
    }

    /**
     * Get event details
     */
    public function getEvent(WeeklyEvent $event): JsonResponse
    {
        $user = Auth::user();

        // Check if user has joined
        $participation = EventParticipation::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        $hasJoined = !is_null($participation);
        $hasCompleted = $hasJoined && $participation->isCompleted();

        // Get questions if user has joined
        $questions = [];
        if ($hasJoined) {
            $questions = EventQuestion::where('event_id', $event->id)
                ->orderBy('display_order')
                ->get();

            // Add user's responses if any
            if ($participation) {
                $responses = EventResponse::where('participation_id', $participation->id)
                    ->get()
                    ->keyBy('question_id');

                $questions->each(function ($question) use ($responses) {
                    $question->user_response = $responses->get($question->id);
                });
            }
        }

        // Get matches if event is completed
        $matches = [];
        if ($event->isCompleted() && $hasCompleted) {
            $matches = EventMatch::where('event_id', $event->id)
                ->where(function ($query) use ($user) {
                    $query->where('user1_id', $user->id)
                        ->orWhere('user2_id', $user->id);
                })
                ->with(['user1', 'user2'])
                ->orderByDesc('compatibility_score')
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'event' => new WeeklyEventResource($event),
                'participation' => $participation ? new EventParticipationResource($participation) : null,
                'questions' => $hasJoined ? $questions : [],
                'matches' => $event->isCompleted() ? EventMatchResource::collection($matches) : [],
                'user_status' => [
                    'has_joined' => $hasJoined,
                    'has_completed' => $hasCompleted,
                    'can_join' => !$hasJoined && !$event->isFull() && $event->isScheduled()
                ]
            ]
        ]);
    }

    /**
     * Join an event
     */
    public function joinEvent(WeeklyEvent $event): JsonResponse
    {
        $user = Auth::user();

        // Check if event can be joined
        if (!$event->isScheduled() && !$event->isActive()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This event cannot be joined at this time'
            ], 400);
        }

        // Check if event is full
        if ($event->isFull()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This event is full'
            ], 400);
        }

        // Check if user already joined
        $existing = EventParticipation::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already joined this event'
            ], 400);
        }

        // Create participation
        $participation = EventParticipation::create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'status' => EventParticipation::STATUS_JOINED,
            'response_data' => [],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully joined the event',
            'data' => [
                'participation' => new EventParticipationResource($participation),
                'event' => new WeeklyEventResource($event)
            ]
        ]);
    }

    /**
     * Submit event responses
     */
    public function submitResponses(Request $request, WeeklyEvent $event): JsonResponse
    {
        $user = Auth::user();

        // Validate event status
        if (!$event->isActive() && !$event->isScheduled()) {
            return response()->json([
                'status' => 'error',
                'message' => 'This event is no longer accepting responses'
            ], 400);
        }

        // Get user participation
        $participation = EventParticipation::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participation) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have not joined this event'
            ], 400);
        }

        if ($participation->isCompleted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already completed this event'
            ], 400);
        }

        // Validate responses
        $request->validate([
            'responses' => 'required|array',
            'responses.*.question_id' => [
                'required',
                'integer',
                Rule::exists('event_questions', 'id')->where('event_id', $event->id)
            ],
            'responses.*.response_value' => 'required|string',
            'responses.*.response_time_ms' => 'nullable|integer'
        ]);

        // Get all questions
        $questions = EventQuestion::where('event_id', $event->id)->get()->keyBy('id');
        $requiredQuestions = $questions->filter(function ($q) {
            return $q->is_required;
        })->pluck('id')->toArray();

        // Check if all required questions are answered
        $answeredQuestions = collect($request->input('responses'))->pluck('question_id')->toArray();
        $missingQuestions = array_diff($requiredQuestions, $answeredQuestions);

        if (!empty($missingQuestions)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Not all required questions have been answered',
                'data' => [
                    'missing_questions' => $missingQuestions
                ]
            ], 400);
        }

        // Save responses
        DB::transaction(function () use ($request, $participation, $questions) {
            // Delete any existing responses
            EventResponse::where('participation_id', $participation->id)->delete();

            // Create new responses
            foreach ($request->input('responses') as $responseData) {
                $question = $questions->get($responseData['question_id']);

                if (!$question) {
                    continue;
                }

                EventResponse::create([
                    'participation_id' => $participation->id,
                    'question_id' => $responseData['question_id'],
                    'response_value' => $responseData['response_value'],
                    'response_metadata' => $responseData['response_metadata'] ?? null,
                    'response_time_ms' => $responseData['response_time_ms'] ?? null
                ]);
            }

            // Mark participation as completed
            $participation->markAsCompleted();

            // Attempt matchmaking if enough participants
            if ($participation->event->participations()->where('status', EventParticipation::STATUS_COMPLETED)->count() >= 10) {
                // Queue matchmaking job (ideally this would be a queued job)
                // For now, process directly if event is ending soon
                if ($participation->event->ends_at->diffInHours(now()) < 6) {
                    $this->matchmakingService->processEventMatches($participation->event);
                }
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Responses submitted successfully',
            'data' => [
                'participation' => new EventParticipationResource($participation->fresh()),
                'completion_time' => $participation->completed_at->toISOString()
            ]
        ]);
    }

    /**
     * Get event matches
     */
    public function getEventMatches(WeeklyEvent $event): JsonResponse
    {
        $user = Auth::user();

        // Check if user participated
        $participation = EventParticipation::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$participation || !$participation->isCompleted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You did not complete this event'
            ], 400);
        }

        // Check if event is completed
        if (!$event->isCompleted() && !$event->isProcessing()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Event matches are not available yet'
            ], 400);
        }

        // Get matches
        $matches = EventMatch::where('event_id', $event->id)
            ->where(function ($query) use ($user) {
                $query->where('user1_id', $user->id)
                    ->orWhere('user2_id', $user->id);
            })
            ->with(['user1', 'user2'])
            ->orderByDesc('compatibility_score')
            ->get();

        // Mark matches as notified
        $matches->each(function ($match) use ($user) {
            if (!$match->is_notified) {
                $match->update([
                    'is_notified' => true,
                    'notified_at' => now()
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'matches' => EventMatchResource::collection($matches),
                'event' => new WeeklyEventResource($event),
                'total_matches' => $matches->count()
            ]
        ]);
    }

    /**
     * Respond to an event match
     */
    public function respondToMatch(Request $request, EventMatch $match): JsonResponse
    {
        $user = Auth::user();

        // Validate if user is part of the match
        if ($match->user1_id !== $user->id && $match->user2_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not part of this match'
            ], 403);
        }

        // Validate request
        $request->validate([
            'accepted' => 'required|boolean'
        ]);

        $accepted = $request->input('accepted');

        // Update match status
        if ($accepted) {
            $match->acceptMatch($user->id);

            // Check if both accepted and create a real match
            if ($match->isAccepted()) {
                $userMatch = $match->convertToUserMatch();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Match accepted, you can now message each other!',
                    'data' => [
                        'match' => new EventMatchResource($match),
                        'user_match_id' => $userMatch ? $userMatch->id : null,
                        'both_accepted' => true
                    ]
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Match accepted, waiting for the other person to respond',
                'data' => [
                    'match' => new EventMatchResource($match),
                    'both_accepted' => false
                ]
            ]);
        } else {
            // If rejected, just mark their side as not accepted
            if ($user->id === $match->user1_id) {
                $match->update(['user1_accepted' => false]);
            } else {
                $match->update(['user2_accepted' => false]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Match declined',
                'data' => [
                    'match' => new EventMatchResource($match)
                ]
            ]);
        }
    }
}
