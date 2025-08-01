<?php

namespace App\Services\Events;

use App\Models\Events\WeeklyEvent;
use App\Models\Events\EventQuestion;
use Illuminate\Support\Facades\DB;

class EventGeneratorService
{
    /**
     * Create a personality quiz event
     */
    public function createPersonalityQuiz(string $title, string $description, array $options = []): WeeklyEvent
    {
        return DB::transaction(function () use ($title, $description, $options) {
            $event = WeeklyEvent::create([
                'event_type' => WeeklyEvent::TYPE_PERSONALITY_QUIZ,
                'title' => $title,
                'description' => $description,
                'event_data' => [
                    'theme' => $options['theme'] ?? 'general',
                    'difficulty' => $options['difficulty'] ?? 'medium',
                    'focus_traits' => $options['focus_traits'] ?? ['openness', 'extraversion'],
                ],
                'starts_at' => $options['starts_at'] ?? now()->addDay(),
                'ends_at' => $options['ends_at'] ?? now()->addDays(3),
                'max_participants' => $options['max_participants'] ?? 1000,
                'status' => WeeklyEvent::STATUS_SCHEDULED,
            ]);

            // Create default questions for personality quiz
            $this->createPersonalityQuizQuestions($event);

            return $event;
        });
    }

    /**
     * Create a scenario challenge event
     */
    public function createScenarioChallenge(string $title, string $description, array $options = []): WeeklyEvent
    {
        return DB::transaction(function () use ($title, $description, $options) {
            $event = WeeklyEvent::create([
                'event_type' => WeeklyEvent::TYPE_SCENARIO_CHALLENGE,
                'title' => $title,
                'description' => $description,
                'event_data' => [
                    'scenario_type' => $options['scenario_type'] ?? 'dilemma',
                    'complexity' => $options['complexity'] ?? 'medium',
                    'theme' => $options['theme'] ?? 'relationship',
                ],
                'starts_at' => $options['starts_at'] ?? now()->addDay(),
                'ends_at' => $options['ends_at'] ?? now()->addDays(3),
                'max_participants' => $options['max_participants'] ?? 1000,
                'status' => WeeklyEvent::STATUS_SCHEDULED,
            ]);

            // Create default questions for scenario challenge
            $this->createScenarioChallengeQuestions($event);

            return $event;
        });
    }

    /**
     * Create a values alignment event
     */
    public function createValuesAlignment(string $title, string $description, array $options = []): WeeklyEvent
    {
        return DB::transaction(function () use ($title, $description, $options) {
            $event = WeeklyEvent::create([
                'event_type' => WeeklyEvent::TYPE_VALUES_ALIGNMENT,
                'title' => $title,
                'description' => $description,
                'event_data' => [
                    'value_categories' => $options['value_categories'] ?? ['ethics', 'lifestyle', 'future'],
                    'depth' => $options['depth'] ?? 'deep',
                ],
                'starts_at' => $options['starts_at'] ?? now()->addDay(),
                'ends_at' => $options['ends_at'] ?? now()->addDays(3),
                'max_participants' => $options['max_participants'] ?? 1000,
                'status' => WeeklyEvent::STATUS_SCHEDULED,
            ]);

            // Create default questions for values alignment
            $this->createValuesAlignmentQuestions($event);

            return $event;
        });
    }

    /**
     * Create a lifestyle matching event
     */
    public function createLifestyleMatching(string $title, string $description, array $options = []): WeeklyEvent
    {
        return DB::transaction(function () use ($title, $description, $options) {
            $event = WeeklyEvent::create([
                'event_type' => WeeklyEvent::TYPE_LIFESTYLE_MATCHING,
                'title' => $title,
                'description' => $description,
                'event_data' => [
                    'focus_areas' => $options['focus_areas'] ?? ['daily_routine', 'leisure', 'health'],
                ],
                'starts_at' => $options['starts_at'] ?? now()->addDay(),
                'ends_at' => $options['ends_at'] ?? now()->addDays(3),
                'max_participants' => $options['max_participants'] ?? 1000,
                'status' => WeeklyEvent::STATUS_SCHEDULED,
            ]);

            // Create default questions for lifestyle matching
            $this->createLifestyleMatchingQuestions($event);

            return $event;
        });
    }

    /**
     * Create personality quiz questions
     */
    private function createPersonalityQuizQuestions(WeeklyEvent $event): void
    {
        $questions = [
            [
                'question_text' => 'How do you typically recharge after a long day?',
                'question_type' => EventQuestion::TYPE_MULTIPLE_CHOICE,
                'options' => [
                    ['value' => 'alone', 'label' => 'Spending time alone'],
                    ['value' => 'few_friends', 'label' => 'With a few close friends'],
                    ['value' => 'social', 'label' => 'At a social gathering'],
                    ['value' => 'outdoors', 'label' => 'Being outdoors in nature'],
                ],
                'psychological_weights' => [
                    'alone' => ['extraversion' => -2, 'neuroticism' => 1],
                    'few_friends' => ['extraversion' => 0, 'agreeableness' => 1],
                    'social' => ['extraversion' => 2, 'openness' => 1],
                    'outdoors' => ['openness' => 1, 'conscientiousness' => 1],
                ],
            ],
            [
                'question_text' => 'When making important decisions, you typically:',
                'question_type' => EventQuestion::TYPE_MULTIPLE_CHOICE,
                'options' => [
                    ['value' => 'logic', 'label' => 'Rely on logic and facts'],
                    ['value' => 'gut', 'label' => 'Trust your gut feeling'],
                    ['value' => 'both', 'label' => 'Consider both logic and emotions'],
                    ['value' => 'others', 'label' => 'Seek advice from others'],
                ],
                'psychological_weights' => [
                    'logic' => ['conscientiousness' => 2, 'neuroticism' => -1],
                    'gut' => ['openness' => 1, 'neuroticism' => 0],
                    'both' => ['openness' => 1, 'conscientiousness' => 1],
                    'others' => ['agreeableness' => 2, 'extraversion' => 1],
                ],
            ],
            [
                'question_text' => 'How do you handle unexpected change?',
                'question_type' => EventQuestion::TYPE_MULTIPLE_CHOICE,
                'options' => [
                    ['value' => 'embrace', 'label' => 'Embrace it as an opportunity'],
                    ['value' => 'adapt', 'label' => 'Adapt but prefer stability'],
                    ['value' => 'resist', 'label' => 'Resist it initially then adjust'],
                    ['value' => 'avoid', 'label' => 'Try to avoid it if possible'],
                ],
                'psychological_weights' => [
                    'embrace' => ['openness' => 2, 'neuroticism' => -1],
                    'adapt' => ['conscientiousness' => 1, 'openness' => 0],
                    'resist' => ['neuroticism' => 1, 'conscientiousness' => 1],
                    'avoid' => ['neuroticism' => 2, 'openness' => -1],
                ],
            ],
        ];

        foreach ($questions as $index => $question) {
            EventQuestion::create([
                'event_id' => $event->id,
                'question_type' => $question['question_type'],
                'question_text' => $question['question_text'],
                'options' => $question['options'],
                'psychological_weights' => $question['psychological_weights'],
                'display_order' => $index + 1,
                'is_required' => true,
            ]);
        }
    }

    // Implement other question creation methods similarly
    private function createScenarioChallengeQuestions(WeeklyEvent $event): void
    {
        // Similar implementation as above with different questions
    }

    private function createValuesAlignmentQuestions(WeeklyEvent $event): void
    {
        // Similar implementation as above with different questions
    }

    private function createLifestyleMatchingQuestions(WeeklyEvent $event): void
    {
        // Similar implementation as above with different questions
    }
}
