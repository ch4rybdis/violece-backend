<?php

namespace App\Services\Psychology;

use App\Models\Psychology\PsychologyQuestion;
use App\Models\Psychology\PsychologyOption;
use App\Models\Psychology\UserResponse;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PsychologicalScoringService
{
    // Trait weights based on research
    private array $traitWeights = [
        'openness' => 0.2,
        'conscientiousness' => 0.2,
        'extraversion' => 0.2,
        'agreeableness' => 0.2,
        'neuroticism' => 0.2,
        'attachment_secure' => 0.3,
        'attachment_anxious' => 0.15,
        'attachment_avoidant' => 0.15,
    ];

    /**
     * Score user's psychological profile based on questionnaire responses
     */
    public function scoreUserResponses(User $user, array $responses): array
    {
        try {
            $scores = $this->calculateTraitScores($responses);
            $personalityType = $this->determineDominantType($scores);
            $attachmentStyle = $this->determineAttachmentStyle($scores);

            // Store scores in profile
            $this->updateUserProfile($user, $scores, $personalityType, $attachmentStyle);

            return [
                'trait_scores' => $scores,
                'personality_type' => $personalityType,
                'attachment_style' => $attachmentStyle,
                'profile_complete' => true
            ];
        } catch (\Exception $e) {
            Log::error('Error scoring psychological profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return [
                'trait_scores' => [],
                'personality_type' => null,
                'attachment_style' => null,
                'profile_complete' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate compatibility between two users
     */
    public function calculateCompatibility(User $user1, User $user2): array
    {
        // Get profiles
        $profile1 = $user1->psychologicalProfile;
        $profile2 = $user2->psychologicalProfile;

        if (!$profile1 || !$profile2) {
            return [
                'score' => 0,
                'compatibility' => 'unknown',
                'description' => 'Compatibility cannot be determined',
                'details' => []
            ];
        }

        // Calculate trait similarity scores
        $traitSimilarities = $this->calculateTraitSimilarities($profile1, $profile2);

        // Calculate complementarity bonus
        $complementarityBonus = $this->calculateComplementarityBonus($profile1, $profile2);

        // Calculate attachment compatibility
        $attachmentCompatibility = $this->calculateAttachmentCompatibility($profile1, $profile2);

        // Calculate overall score (scale 0-100)
        $overallScore = min(100, max(0,
            $traitSimilarities['overall_similarity'] * 50 +
            $complementarityBonus * 20 +
            $attachmentCompatibility['score'] * 30
        ));

        // Determine compatibility level
        $compatibilityLevel = $this->getCompatibilityLevel($overallScore);

        return [
            'score' => round($overallScore),
            'compatibility' => $compatibilityLevel,
            'description' => $this->getCompatibilityDescription($compatibilityLevel),
            'details' => [
                'trait_similarities' => $traitSimilarities,
                'complementarity_bonus' => $complementarityBonus,
                'attachment_compatibility' => $attachmentCompatibility
            ]
        ];
    }

    // Add all the private helper methods for calculations...

    private function calculateTraitScores(array $responses): array
    {
        // Initialize trait scores
        $scores = [
            'openness' => 0,
            'conscientiousness' => 0,
            'extraversion' => 0,
            'agreeableness' => 0,
            'neuroticism' => 0,
            'attachment_secure' => 0,
            'attachment_anxious' => 0,
            'attachment_avoidant' => 0,
        ];

        $questionCounts = array_fill_keys(array_keys($scores), 0);

        // Process each response
        foreach ($responses as $response) {
            $questionId = $response['question_id'];
            $optionId = $response['option_id'];

            // Get option and its psychological weights
            $option = PsychologyOption::find($optionId);
            if (!$option) continue;

            $weights = $option->psychological_weights ?? [];

            // Add weights to scores
            foreach ($weights as $trait => $weight) {
                if (isset($scores[$trait])) {
                    $scores[$trait] += $weight;
                    $questionCounts[$trait]++;
                }
            }
        }

        // Normalize scores (0-10 scale)
        foreach ($scores as $trait => $score) {
            $count = $questionCounts[$trait];
            $scores[$trait] = $count > 0 ? min(10, max(0, ($score / $count) * 5 + 5)) : 5;
        }

        return $scores;
    }

    private function determineDominantType(array $scores): string
    {
        // Sort Big Five traits by score
        $bigFiveScores = [
            'openness' => $scores['openness'],
            'conscientiousness' => $scores['conscientiousness'],
            'extraversion' => $scores['extraversion'],
            'agreeableness' => $scores['agreeableness'],
            'neuroticism' => $scores['neuroticism'],
        ];

        arsort($bigFiveScores);

        // Get top two traits
        $topTraits = array_keys(array_slice($bigFiveScores, 0, 2, true));

        // Map to personality types
        $typeMap = [
            'openness_extraversion' => 'Explorer',
            'openness_conscientiousness' => 'Analyst',
            'openness_agreeableness' => 'Diplomat',
            'openness_neuroticism' => 'Visionary',
            'conscientiousness_extraversion' => 'Director',
            'conscientiousness_agreeableness' => 'Guardian',
            'conscientiousness_neuroticism' => 'Perfectionist',
            'extraversion_agreeableness' => 'Enthusiast',
            'extraversion_neuroticism' => 'Performer',
            'agreeableness_neuroticism' => 'Mediator',
        ];

        // Create key for type map
        sort($topTraits);
        $typeKey = implode('_', $topTraits);

        return $typeMap[$typeKey] ?? 'Balanced';
    }

    private function determineAttachmentStyle(array $scores): string
    {
        $secure = $scores['attachment_secure'];
        $anxious = $scores['attachment_anxious'];
        $avoidant = $scores['attachment_avoidant'];

        if ($secure >= max($anxious, $avoidant)) {
            return 'Secure';
        } elseif ($anxious > $avoidant) {
            return 'Anxious';
        } else {
            return 'Avoidant';
        }
    }

    private function updateUserProfile(User $user, array $scores, string $personalityType, string $attachmentStyle): void
    {
        // Create or update user's psychological profile
        $user->psychologicalProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'trait_scores' => $scores,
                'personality_type' => $personalityType,
                'attachment_style' => $attachmentStyle,
                'is_complete' => true,
                'completed_at' => now(),
            ]
        );
    }

    private function calculateTraitSimilarities(object $profile1, object $profile2): array
    {
        $traits = ['openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism'];
        $similarities = [];
        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($traits as $trait) {
            $score1 = $profile1->trait_scores[$trait] ?? 5;
            $score2 = $profile2->trait_scores[$trait] ?? 5;

            // Calculate similarity (0-1 scale, where 1 is identical)
            $similarity = 1 - (abs($score1 - $score2) / 10);
            $similarities[$trait] = $similarity;

            // Add to weighted sum
            $weight = $this->traitWeights[$trait];
            $weightedSum += $similarity * $weight;
            $totalWeight += $weight;
        }

        // Calculate overall similarity
        $overallSimilarity = $totalWeight > 0 ? $weightedSum / $totalWeight : 0.5;

        return [
            'trait_similarities' => $similarities,
            'overall_similarity' => $overallSimilarity
        ];
    }

    private function calculateComplementarityBonus(object $profile1, object $profile2): float
    {
        // Complementarity is a bonus for traits where differences can be beneficial
        // For example, an extrovert and introvert can complement each other

        $score1 = $profile1->trait_scores['extraversion'] ?? 5;
        $score2 = $profile2->trait_scores['extraversion'] ?? 5;

        // Extraversion complementarity (highest when one is high and one is low)
        $extraversionComp = (10 - abs($score1 - $score2)) / 10;
        $extraversionDiff = abs($score1 - $score2) / 10;

        // The more different they are on extraversion, the higher the bonus
        // But we want this to be non-linear, so we apply a curve
        $complementarityBonus = $extraversionDiff * (1 - pow($extraversionDiff, 2));

        return $complementarityBonus;
    }

    private function calculateAttachmentCompatibility(object $profile1, object $profile2): array
    {
        $style1 = $profile1->attachment_style;
        $style2 = $profile2->attachment_style;

        // Compatibility matrix (0-1 scale)
        $matrix = [
            'Secure' => [
                'Secure' => 1.0,
                'Anxious' => 0.7,
                'Avoidant' => 0.6
            ],
            'Anxious' => [
                'Secure' => 0.7,
                'Anxious' => 0.4,
                'Avoidant' => 0.2
            ],
            'Avoidant' => [
                'Secure' => 0.6,
                'Anxious' => 0.2,
                'Avoidant' => 0.3
            ]
        ];

        $score = $matrix[$style1][$style2] ?? 0.5;

        return [
            'score' => $score,
            'styles' => [$style1, $style2],
            'description' => $this->getAttachmentDescription($style1, $style2)
        ];
    }

    private function getCompatibilityLevel(float $score): string
    {
        if ($score >= 85) return 'Exceptional';
        if ($score >= 70) return 'High';
        if ($score >= 50) return 'Moderate';
        if ($score >= 30) return 'Low';
        return 'Minimal';
    }

    private function getCompatibilityDescription(string $level): string
    {
        $descriptions = [
            'Exceptional' => 'Remarkable psychological alignment with strong potential for a deep connection',
            'High' => 'Strong compatibility suggesting a naturally harmonious connection',
            'Moderate' => 'Good foundation with some differences that could be complementary',
            'Low' => 'Some compatibility with notable differences requiring mutual understanding',
            'Minimal' => 'Limited psychological alignment suggesting potential challenges'
        ];

        return $descriptions[$level] ?? 'Compatibility level undetermined';
    }

    private function getAttachmentDescription(string $style1, string $style2): string
    {
        if ($style1 === 'Secure' && $style2 === 'Secure') {
            return 'Two secure individuals create a healthy, stable foundation built on mutual trust';
        }

        if (($style1 === 'Secure' && $style2 === 'Anxious') || ($style1 === 'Anxious' && $style2 === 'Secure')) {
            return 'Secure partner provides stability while anxious partner brings emotional depth';
        }

        if (($style1 === 'Secure' && $style2 === 'Avoidant') || ($style1 === 'Avoidant' && $style2 === 'Secure')) {
            return 'Secure partner brings consistency while avoidant partner values independence';
        }

        if ($style1 === 'Anxious' && $style2 === 'Anxious') {
            return 'Potential for emotional intensity requiring clear communication about needs';
        }

        if (($style1 === 'Anxious' && $style2 === 'Avoidant') || ($style1 === 'Avoidant' && $style2 === 'Anxious')) {
            return 'Classic anxious-avoidant dynamic that requires conscious effort to balance needs';
        }

        if ($style1 === 'Avoidant' && $style2 === 'Avoidant') {
            return 'Both value independence which may create comfort but potential emotional distance';
        }

        return 'Attachment styles suggest a unique dynamic worth exploring';
    }
}
