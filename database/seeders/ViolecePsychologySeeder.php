<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Psychology\{QuestionSet, PsychologicalQuestion, QuestionOption};

class ViolecePsychologySeeder extends Seeder
{
    public function run(): void
    {
        // Create default question set
        $questionSet = QuestionSet::create([
            'name' => 'Violece Core Personality Assessment',
            'version' => '1.0',
            'description' => 'Black Mirror inspired psychological profiling for meaningful connections',
            'is_active' => true,
            'is_default' => true,
            'total_questions' => 4,
            'estimated_duration_minutes' => 3,
        ]);

        $this->createRainyDayScenario($questionSet);
        $this->createSocialGatheringScenario($questionSet);
        $this->createDecisionMakingScenario($questionSet);
        $this->createConflictResolutionScenario($questionSet);
    }

    private function createRainyDayScenario(QuestionSet $questionSet): void
    {
        $question = PsychologicalQuestion::create([
            'question_set_id' => $questionSet->id,
            'order_sequence' => 1,
            'content_key' => 'rainy_day_preference',
            'category' => 'lifestyle',
            'title' => 'A Perfect Rainy Evening',
            'scenario_text' => 'It\'s raining heavily outside, and you have the entire evening to yourself...',
            'video_filename' => 'rainy_evening_scenario.mp4', // 20s looping video
            'image_filename' => 'rainy_evening_fallback.jpg',
            'psychological_weights' => [
                'introversion' => 0.8,
                'comfort_seeking' => 0.6,
                'spontaneity' => 0.7
            ],
            'is_required' => true,
        ]);

        // Option 1: Stay Inside (Comfort)
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'cozy_indoor',
            'order_sequence' => 1,
            'text' => 'Light candles, make tea, and read a good book',
            'visual_content' => 'cozy_reading_evening.mp4', // 15s peaceful video
            'trait_impacts' => [
                'introversion' => +2,
                'comfort_seeking' => +2,
                'routine_preference' => +1,
                'mindfulness' => +1
            ]
        ]);

        // Option 2: Go Outside (Adventure)
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'rain_adventure',
            'order_sequence' => 2,
            'text' => 'Put on boots and take a walk in the rain',
            'visual_content' => 'rain_walking_adventure.mp4', // 15s dynamic video
            'trait_impacts' => [
                'spontaneity' => +3,
                'adventure_seeking' => +2,
                'unconventional' => +2,
                'nature_connection' => +1
            ]
        ]);

        // Option 3: Social Connection
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'social_evening',
            'order_sequence' => 3,
            'text' => 'Call a friend and invite them over for dinner',
            'visual_content' => 'intimate_dinner_prep.mp4', // 15s warm video
            'trait_impacts' => [
                'social_connection' => +2,
                'nurturing' => +2,
                'spontaneity' => +1,
                'extroversion' => +1
            ]
        ]);
    }

    private function createSocialGatheringScenario(QuestionSet $questionSet): void
    {
        $question = PsychologicalQuestion::create([
            'question_set_id' => $questionSet->id,
            'order_sequence' => 2,
            'content_key' => 'social_gathering_preference',
            'category' => 'social_behavior',
            'title' => 'Your Ideal Social Setting',
            'scenario_text' => 'You\'re planning to celebrate a personal achievement...',
            'video_filename' => 'celebration_planning_scenario.mp4',
            'image_filename' => 'celebration_planning_fallback.jpg',
            'psychological_weights' => [
                'extroversion' => 0.9,
                'social_energy' => 0.8,
                'intimacy_preference' => 0.7
            ],
            'is_required' => true,
        ]);

        // Option 1: Big Party
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'big_celebration',
            'order_sequence' => 1,
            'text' => 'Throw a big party with music, dancing, and lots of people',
            'visual_content' => 'vibrant_party_scene.mp4',
            'trait_impacts' => [
                'extroversion' => +3,
                'social_energy' => +2,
                'celebration_style' => +2,
                'attention_comfort' => +1
            ]
        ]);

        // Option 2: Intimate Gathering
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'intimate_dinner',
            'order_sequence' => 2,
            'text' => 'Have an intimate dinner with your closest friends',
            'visual_content' => 'intimate_gathering_scene.mp4',
            'trait_impacts' => [
                'intimacy_preference' => +3,
                'deep_connection' => +2,
                'quality_over_quantity' => +2,
                'meaningful_relationships' => +1
            ]
        ]);

        // Option 3: Solo Celebration
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'solo_treat',
            'order_sequence' => 3,
            'text' => 'Treat yourself to something special, just for you',
            'visual_content' => 'self_care_celebration.mp4',
            'trait_impacts' => [
                'self_sufficiency' => +3,
                'introversion' => +2,
                'self_love' => +2,
                'independence' => +1
            ]
        ]);
    }

    private function createDecisionMakingScenario(QuestionSet $questionSet): void
    {
        $question = PsychologicalQuestion::create([
            'question_set_id' => $questionSet->id,
            'order_sequence' => 3,
            'content_key' => 'decision_making_style',
            'category' => 'decision_making',
            'title' => 'Life-Changing Opportunity',
            'scenario_text' => 'You receive an unexpected job offer in a different city...',
            'video_filename' => 'life_decision_scenario.mp4',
            'image_filename' => 'life_decision_fallback.jpg',
            'psychological_weights' => [
                'logic_vs_emotion' => 0.9,
                'risk_tolerance' => 0.8,
                'planning_style' => 0.7
            ],
            'is_required' => true,
        ]);

        // Option 1: Analytical Approach
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'analytical_decision',
            'order_sequence' => 1,
            'text' => 'Make a pros/cons list and research everything thoroughly',
            'visual_content' => 'analytical_planning.mp4',
            'trait_impacts' => [
                'logical_thinking' => +3,
                'methodical_approach' => +2,
                'risk_assessment' => +2,
                'planning_oriented' => +1
            ]
        ]);

        // Option 2: Intuitive Approach
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'intuitive_decision',
            'order_sequence' => 2,
            'text' => 'Trust your gut feeling and follow your heart',
            'visual_content' => 'intuitive_reflection.mp4',
            'trait_impacts' => [
                'emotional_intelligence' => +3,
                'intuitive_thinking' => +2,
                'authenticity' => +2,
                'risk_taking' => +1
            ]
        ]);

        // Option 3: Collaborative Approach
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'collaborative_decision',
            'order_sequence' => 3,
            'text' => 'Discuss with family and friends to get different perspectives',
            'visual_content' => 'collaborative_discussion.mp4',
            'trait_impacts' => [
                'social_validation' => +2,
                'collaborative_thinking' => +2,
                'relationship_priority' => +2,
                'support_seeking' => +1
            ]
        ]);
    }

    private function createConflictResolutionScenario(QuestionSet $questionSet): void
    {
        $question = PsychologicalQuestion::create([
            'question_set_id' => $questionSet->id,
            'order_sequence' => 4,
            'content_key' => 'conflict_resolution_style',
            'category' => 'relationship_dynamics',
            'title' => 'Relationship Tension',
            'scenario_text' => 'You have a disagreement with someone important to you...',
            'video_filename' => 'relationship_conflict_scenario.mp4',
            'image_filename' => 'relationship_conflict_fallback.jpg',
            'psychological_weights' => [
                'communication_style' => 0.9,
                'conflict_tolerance' => 0.8,
                'empathy_level' => 0.8
            ],
            'is_required' => true,
        ]);

        // Option 1: Direct Communication
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'direct_approach',
            'order_sequence' => 1,
            'text' => 'Address the issue directly and openly discuss your feelings',
            'visual_content' => 'direct_conversation.mp4',
            'trait_impacts' => [
                'direct_communication' => +3,
                'conflict_engagement' => +2,
                'assertiveness' => +2,
                'honesty' => +1
            ]
        ]);

        // Option 2: Gentle Approach
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'gentle_approach',
            'order_sequence' => 2,
            'text' => 'Give them space first, then gently bring up your concerns',
            'visual_content' => 'gentle_conversation.mp4',
            'trait_impacts' => [
                'emotional_sensitivity' => +3,
                'patience' => +2,
                'diplomatic_communication' => +2,
                'empathy' => +1
            ]
        ]);

        // Option 3: Understanding First
        QuestionOption::create([
            'question_id' => $question->id,
            'option_key' => 'understanding_approach',
            'order_sequence' => 3,
            'text' => 'Try to understand their perspective before sharing yours',
            'visual_content' => 'empathetic_listening.mp4',
            'trait_impacts' => [
                'active_listening' => +3,
                'empathy' => +3,
                'perspective_taking' => +2,
                'relationship_focus' => +1
            ]
        ]);
    }
}
