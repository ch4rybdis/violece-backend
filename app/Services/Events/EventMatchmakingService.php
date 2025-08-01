<?php

namespace App\Services\Events;

use App\Models\Events\WeeklyEvent;
use App\Models\Events\EventParticipation;
use App\Models\Events\EventMatch;
use App\Models\Events\EventResponse;
use App\Services\Matching\CompatibilityScoringService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EventMatchmakingService
{
    protected CompatibilityScoringService $compatibilityService;

    public function __construct(CompatibilityScoringService $compatibilityService)
    {
        $this->compatibilityService = $compatibilityService;
    }

    /**
     * Process event responses and create matches
     */
    public function processEventMatches(WeeklyEvent $event): Collection
    {
        // Get all completed participations
        $participations = EventParticipation::where('event_id', $event->id)
            ->where('status', EventParticipation::STATUS_COMPLETED)
            ->with(['user', 'responses.question'])
            ->get();

        // If not enough participants, return empty collection
        if ($participations->count() < 2) {
            return collect();
        }

        // Calculate compatibility between all participant pairs
        $matches = $this->calculateCompatibilityMatrix($event, $participations);

        // Sort by compatibility score (highest first)
        $matches = $matches->sortByDesc('compatibility_score')->values();

        // Create match records
        $this->createMatchRecords($event, $matches);

        return $matches;
    }

    /**
     * Calculate compatibility matrix between all participants
     */
    private function calculateCompatibilityMatrix(WeeklyEvent $event, Collection $participations): Collection
    {
        $matches = collect();

        // Get all participant pairs
        for ($i = 0; $i < $participations->count(); $i++) {
            for ($j = $i + 1; $j < $participations->count(); $j++) {
                $participation1 = $participations[$i];
                $participation2 = $participations[$j];

                // Skip if users are the same (shouldn't happen, but just in case)
                if ($participation1->user_id === $participation2->user_id) {
                    continue;
                }

                // Calculate compatibility based on event type
                $compatibilityScore = $this->calculatePairCompatibility(
                    $event,
                    $participation1,
                    $participation2
                );

                // Generate match reasons
                $matchReasons = $this->generateMatchReasons(
                    $event,
                    $participation1,
                    $participation2
                );

                // Add to matches collection
                $matches->push([
                    'user1_id' => $participation1->user_id,
                    'user2_id' => $participation2->user_id,
                    'compatibility_score' => $compatibilityScore,
                    'match_reasons' => $matchReasons,
                ]);
            }
        }

        return $matches;
    }

    /**
     * Calculate compatibility between two participants
     */
    private function calculatePairCompatibility(
        WeeklyEvent $event,
        EventParticipation $participation1,
        EventParticipation $participation2
    ): float {
        // Base compatibility on event type
        switch ($event->event_type) {
            case WeeklyEvent::TYPE_PERSONALITY_QUIZ:
                return $this->calculatePersonalityCompatibility($participation1, $participation2);

            case WeeklyEvent::TYPE_SCENARIO_CHALLENGE:
                return $this->calculateScenarioCompatibility($participation1, $participation2);

            case WeeklyEvent::TYPE_VALUES_ALIGNMENT:
                return $this->calculateValuesCompatibility($participation1, $participation2);

            case WeeklyEvent::TYPE_LIFESTYLE_MATCHING:
                return $this->calculateLifestyleCompatibility($participation1, $participation2);

            default:
                // Fallback to generic compatibility
                return $this->calculateGenericCompatibility($participation1, $participation2);
        }
    }

    /**
     * Calculate personality quiz compatibility
     */
    private function calculatePersonalityCompatibility(
        EventParticipation $participation1,
        EventParticipation $participation2
    ): float {
        // Get responses
        $responses1 = $participation1->responses;
        $responses2 = $participation2->responses;

        // Calculate trait scores
        $traitScores1 = $this->calculateTraitScores($responses1);
        $traitScores2 = $this->calculateTraitScores($responses2);

        // Calculate compatibility based on trait alignment
        $compatibility = 0;
        $traitCount = 0;

        foreach ($traitScores1 as $trait => $score1) {
            if (isset($traitScores2[$trait])) {
                $score2 = $traitScores2[$trait];

                // Complementary traits (opposites attract)
                if (in_array($trait, ['extraversion', 'openness'])) {
                    $compatibility += 100 - abs($score1 - $score2);
                }
                // Similar traits (birds of a feather)
                else {
                    $compatibility += 100 - abs($score1 - $score2);
                }

                $traitCount++;
            }
        }

        // Normalize to 0-100 scale
        return $traitCount > 0 ? min(99, max(1, $compatibility / $traitCount)) : 50;
    }

    /**
     * Calculate trait scores from responses
     */
    private function calculateTraitScores(Collection $responses): array
    {
        $traitScores = [
            'openness' => 0,
            'conscientiousness' => 0,
            'extraversion' => 0,
            'agreeableness' => 0,
            'neuroticism' => 0,
        ];

        $traitCounts = array_fill_keys(array_keys($traitScores), 0);

        foreach ($responses as $response) {
            $question = $response->question;
            $value = $response->response_value;

            // Skip if no psychological weights or value not found
            if (empty($question->psychological_weights) || !isset($question->psychological_weights[$value])) {
                continue;
            }

            // Add trait scores
            foreach ($question->psychological_weights[$value] as $trait => $weight) {
                if (isset($traitScores[$trait])) {
                    $traitScores[$trait] += $weight;
                    $traitCounts[$trait]++;
                }
            }
        }

        // Normalize scores to 0-100
        foreach ($traitScores as $trait => $score) {
            if ($traitCounts[$trait] > 0) {
                // Convert from -2 to +2 scale to 0-100 scale
                $normalizedScore = 50 + ($score / ($traitCounts[$trait] * 2)) * 50;
                $traitScores[$trait] = min(100, max(0, $normalizedScore));
            } else {
                $traitScores[$trait] = 50; // Default midpoint if no data
            }
        }

        return $traitScores;
    }

    // Implement other compatibility calculation methods

    /**
     * Generate match reasons based on responses
     */
    private function generateMatchReasons(
        WeeklyEvent $event,
        EventParticipation $participation1,
        EventParticipation $participation2
    ): array {
        $reasons = [];

        // Get responses
        $responses1 = $participation1->responses;
        $responses2 = $participation2->responses;

        // Find questions with similar or complementary answers
        foreach ($responses1 as $response1) {
            $response2 = $responses2->firstWhere('question_id', $response1->question_id);

            if (!$response2) {
                continue;
            }

            $question = $response1->question;

            // Check if answers are the same
            if ($response1->response_value === $response2->response_value) {
                $reasons[] = [
                    'type' => 'similar',
                    'question' => $question->question_text,
                    'answer' => $response1->getLabel() ?? $response1->response_value,
                ];
            }
            // Check for complementary answers based on psychological weights
            elseif (!empty($question->psychological_weights)) {
                $weights1 = $question->psychological_weights[$response1->response_value] ?? [];
                $weights2 = $question->psychological_weights[$response2->response_value] ?? [];

                $complementary = false;

                foreach ($weights1 as $trait => $weight1) {
                    if (isset($weights2[$trait]) && $weight1 * $weights2[$trait] < 0) {
                        $complementary = true;
                        break;
                    }
                }

                if ($complementary) {
                    $reasons[] = [
                        'type' => 'complementary',
                        'question' => $question->question_text,
                        'answer1' => $response1->getLabel() ?? $response1->response_value,
                        'answer2' => $response2->getLabel() ?? $response2->response_value,
                    ];
                }
            }
        }

        // Limit to top 3 reasons
        return array_slice($reasons, 0, 3);
    }

    /**
     * Create match records in database
     */
    private function createMatchRecords(WeeklyEvent $event, Collection $matches): void
    {
        DB::transaction(function () use ($event, $matches) {
            foreach ($matches as $match) {
                // Skip if match already exists
                $existingMatch = EventMatch::where('event_id', $event->id)
                    ->where(function ($query) use ($match) {
                        $query->where(function ($q) use ($match) {
                            $q->where('user1_id', $match['user1_id'])
                                ->where('user2_id', $match['user2_id']);
                        })->orWhere(function ($q) use ($match) {
                            $q->where('user1_id', $match['user2_id'])
                                ->where('user2_id', $match['user1_id']);
                        });
                    })
                    ->first();

                if ($existingMatch) {
                    continue;
                }

                // Create new match record
                EventMatch::create([
                    'event_id' => $event->id,
                    'user1_id' => $match['user1_id'],
                    'user2_id' => $match['user2_id'],
                    'compatibility_score' => $match['compatibility_score'],
                    'match_reasons' => $match['match_reasons'],
                    'is_notified' => false,
                ]);
            }

            // Update event status to processing
            $event->update(['status' => WeeklyEvent::STATUS_PROCESSING]);
        });
    }

    // Implement other compatibility calculation methods
    private function calculateScenarioCompatibility(
        EventParticipation $participation1,
        EventParticipation $participation2
    ): float {
        // Implementation for scenario compatibility
        return 50.0; // Placeholder
    }

    private function calculateValuesCompatibility(
        EventParticipation $participation1,
        EventParticipation $participation2
    ): float {
        // Implementation for values compatibility
        return 50.0; // Placeholder
    }

    private function calculateLifestyleCompatibility(
        EventParticipation $participation1,
        EventParticipation $participation2
    ): float {
        // Get all responses
        $responses1 = $participation1->responses;
        $responses2 = $participation2->responses;

        // Count matches and similarities
        $totalQuestions = 0;
        $similarityScore = 0;

        // Compare each pair of responses
        foreach ($responses1 as $response1) {
            $response2 = $responses2->firstWhere('question_id', $response1->question_id);

            if (!$response2) {
                continue;
            }

            $totalQuestions++;

            // For lifestyle matching, we want similar answers for most questions
            if ($response1->response_value === $response2->response_value) {
                $similarityScore += 1.0; // Full match
            } else {
                // Check how different the responses are (for scaled responses)
                $question = $response1->question;

                if ($question->isScale()) {
                    // Calculate how close the values are on a scale
                    $value1 = (int) $response1->response_value;
                    $value2 = (int) $response2->response_value;
                    $maxDiff = 5; // Assume 5-point scale

                    $diff = abs($value1 - $value2);
                    $similarityScore += 1 - ($diff / $maxDiff);
                }
                // For multiple choice that didn't match, give partial credit for certain combinations
                else if ($question->isMultipleChoice()) {
                    // Could implement specific combinations that still get partial credit
                    $similarityScore += 0.2; // Small partial credit
                }
            }
        }

        // Calculate final score (0-100 scale)
        if ($totalQuestions === 0) {
            return 50.0; // Default if no comparable questions
        }

        return min(99, max(1, ($similarityScore / $totalQuestions) * 100));
    }

    private function calculateGenericCompatibility(
        EventParticipation $participation1,
        EventParticipation $participation2
    ): float {
        // Fallback generic compatibility calculation
        $responses1 = $participation1->responses;
        $responses2 = $participation2->responses;

        $matchCount = 0;
        $totalQuestions = 0;

        foreach ($responses1 as $response1) {
            $response2 = $responses2->firstWhere('question_id', $response1->question_id);

            if (!$response2) {
                continue;
            }

            $totalQuestions++;

            if ($response1->response_value === $response2->response_value) {
                $matchCount++;
            }
        }

        if ($totalQuestions === 0) {
            return 50.0;
        }

        // Basic ratio of matching answers, scaled to 1-99 range
        return min(99, max(1, ($matchCount / $totalQuestions) * 100));
    }
}
