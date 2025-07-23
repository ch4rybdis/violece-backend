<?php

// app/Services/Matching/CompatibilityScoringService.php

namespace App\Services\Matching;

use App\Models\User;
use App\Models\Psychology\UserPsychologicalProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CompatibilityScoringService
{
    /**
     * Academic research-based compatibility weights
     * Sources: Anderson (2017), Levy et al. (2019), Gerlach et al. (2018)
     */
    private const COMPATIBILITY_WEIGHTS = [
        'personality_similarity' => 0.40,  // Big Five trait alignment
        'attachment_compatibility' => 0.25, // Attachment style compatibility
        'behavioral_patterns' => 0.20,     // App usage and communication patterns
        'values_alignment' => 0.10,        // Derived from questionnaire responses
        'complementarity_bonus' => 0.05    // Beneficial differences
    ];

    /**
     * Big Five similarity scoring weights
     * Research shows varying importance of trait similarity
     */
    private const BIG_FIVE_SIMILARITY_WEIGHTS = [
        'agreeableness' => 0.35,      // Highest predictor of relationship satisfaction
        'conscientiousness' => 0.25,  // Strong predictor of stability
        'neuroticism' => 0.20,        // Lower neuroticism crucial (negative correlation)
        'extraversion' => 0.12,       // Moderate importance
        'openness' => 0.08           // Lowest direct relationship impact
    ];

    /**
     * Attachment style compatibility matrix
     * Based on Hazan & Shaver (1987), Brennan et al. (1998)
     */
    private const ATTACHMENT_COMPATIBILITY_MATRIX = [
        'secure' => [
            'secure' => 0.95,      // Ideal pairing
            'anxious' => 0.85,     // Secure can provide stability
            'avoidant' => 0.70,    // Workable with effort
            'mixed' => 0.80
        ],
        'anxious' => [
            'secure' => 0.90,      // Anxious benefits from secure partner
            'anxious' => 0.40,     // Can amplify each other's anxiety
            'avoidant' => 0.25,    // Problematic pairing (pursue-withdraw)
            'mixed' => 0.65
        ],
        'avoidant' => [
            'secure' => 0.75,      // Secure can bridge the gap
            'anxious' => 0.30,     // Avoidant triggers anxious fears
            'avoidant' => 0.60,    // Can work but may lack intimacy
            'mixed' => 0.70
        ],
        'mixed' => [
            'secure' => 0.85,
            'anxious' => 0.70,
            'avoidant' => 0.75,
            'mixed' => 0.80
        ]
    ];

    /**
     * Calculate comprehensive compatibility score between two users
     */
    public function calculateCompatibilityScore(User $user1, User $user2): array
    {
        $profile1 = $this->getUserProfile($user1->id);
        $profile2 = $this->getUserProfile($user2->id);

        if (!$profile1 || !$profile2) {
            return [
                'total_score' => 0,
                'error' => 'Missing psychological profiles'
            ];
        }

        // Calculate component scores
        $personalityScore = $this->calculatePersonalityCompatibility($profile1, $profile2);
        $attachmentScore = $this->calculateAttachmentCompatibility($profile1, $profile2);
        $behavioralScore = $this->calculateBehavioralCompatibility($user1, $user2);
        $valuesScore = $this->calculateValuesAlignment($profile1, $profile2);
        $complementarityBonus = $this->calculateComplementarityBonus($profile1, $profile2);

        // Calculate weighted total score
        $totalScore = (
            $personalityScore * self::COMPATIBILITY_WEIGHTS['personality_similarity'] +
            $attachmentScore * self::COMPATIBILITY_WEIGHTS['attachment_compatibility'] +
            $behavioralScore * self::COMPATIBILITY_WEIGHTS['behavioral_patterns'] +
            $valuesScore * self::COMPATIBILITY_WEIGHTS['values_alignment'] +
            $complementarityBonus * self::COMPATIBILITY_WEIGHTS['complementarity_bonus']
        );

        // Apply research-based adjustments
        $adjustedScore = $this->applyResearchBasedAdjustments($totalScore, $profile1, $profile2);

        return [
            'total_score' => round(min(99, max(1, $adjustedScore)), 1),
            'component_scores' => [
                'personality_similarity' => round($personalityScore, 1),
                'attachment_compatibility' => round($attachmentScore, 1),
                'behavioral_patterns' => round($behavioralScore, 1),
                'values_alignment' => round($valuesScore, 1),
                'complementarity_bonus' => round($complementarityBonus, 1)
            ],
            'detailed_analysis' => [
                'strongest_connections' => $this->identifyStrongestConnections($profile1, $profile2),
                'potential_challenges' => $this->identifyPotentialChallenges($profile1, $profile2),
                'relationship_style_prediction' => $this->predictRelationshipStyle($profile1, $profile2)
            ]
        ];
    }

    private function calculatePersonalityCompatibility(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): float
    {
        $traits = ['openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism'];
        $totalSimilarity = 0.0;

        foreach ($traits as $trait) {
            $score1 = $profile1->{$trait . '_score'};
            $score2 = $profile2->{$trait . '_score'};

            // Calculate similarity (inverse of difference, normalized)
            $difference = abs($score1 - $score2);
            $similarity = 100 - $difference; // 0-100 scale

            // Apply trait-specific weights
            $weight = self::BIG_FIVE_SIMILARITY_WEIGHTS[$trait];

            // Special handling for neuroticism (lower is better for compatibility)
            if ($trait === 'neuroticism') {
                // Bonus if both have low neuroticism
                $avgNeuroticism = ($score1 + $score2) / 2;
                if ($avgNeuroticism < 40) {
                    $similarity += 20; // Bonus for both being emotionally stable
                }
            }

            $totalSimilarity += $similarity * $weight;
        }

        return min(100, $totalSimilarity);
    }

    private function calculateAttachmentCompatibility(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): float
    {
        $style1 = $profile1->primary_attachment_style;
        $style2 = $profile2->primary_attachment_style;

        $baseCompatibility = self::ATTACHMENT_COMPATIBILITY_MATRIX[$style1][$style2] ?? 0.5;

        // Fine-tune based on attachment scores
        $secureBonus = 0;
        if ($profile1->secure_attachment_score > 60 || $profile2->secure_attachment_score > 60) {
            $secureBonus = 10; // Having one secure partner improves compatibility
        }

        // Penalty for high anxious-avoidant combination
        $anxiousAvoidantPenalty = 0;
        if (($profile1->anxious_attachment_score > 70 && $profile2->avoidant_attachment_score > 70) ||
            ($profile1->avoidant_attachment_score > 70 && $profile2->anxious_attachment_score > 70)) {
            $anxiousAvoidantPenalty = -15; // Research shows this is problematic
        }

        return max(0, min(100, ($baseCompatibility * 100) + $secureBonus + $anxiousAvoidantPenalty));
    }

    private function calculateBehavioralCompatibility(User $user1, User $user2): float
    {
        // Analyze app usage patterns, response times, activity levels
        $behavioral1 = $this->analyzeBehavioralPatterns($user1);
        $behavioral2 = $this->analyzeBehavioralPatterns($user2);

        $compatibilityFactors = [];

        // Response time compatibility
        $responseTimeDiff = abs($behavioral1['avg_response_time'] - $behavioral2['avg_response_time']);
        $responseTimeScore = max(0, 100 - ($responseTimeDiff / 1000 * 10)); // Penalize large differences
        $compatibilityFactors[] = $responseTimeScore * 0.3;

        // Activity level compatibility
        $activityDiff = abs($behavioral1['activity_level'] - $behavioral2['activity_level']);
        $activityScore = max(0, 100 - $activityDiff * 2);
        $compatibilityFactors[] = $activityScore * 0.25;

        // Communication style compatibility
        $commStyleScore = $this->compareTextPatterns($behavioral1['text_patterns'], $behavioral2['text_patterns']);
        $compatibilityFactors[] = $commStyleScore * 0.25;

        // Online timing overlap
        $timingOverlap = $this->calculateTimingOverlap($behavioral1['active_hours'], $behavioral2['active_hours']);
        $compatibilityFactors[] = $timingOverlap * 0.2;

        return array_sum($compatibilityFactors);
    }

    private function calculateValuesAlignment(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): float
    {
        // Extract values from compatibility keywords and response patterns
        $keywords1 = $profile1->compatibility_keywords ?? [];
        $keywords2 = $profile2->compatibility_keywords ?? [];

        if (empty($keywords1) || empty($keywords2)) {
            return 50; // Neutral score if insufficient data
        }

        // Calculate keyword overlap
        $commonKeywords = array_intersect($keywords1, $keywords2);
        $totalKeywords = array_unique(array_merge($keywords1, $keywords2));

        $overlapScore = count($totalKeywords) > 0 ? (count($commonKeywords) / count($totalKeywords)) * 100 : 50;

        // Bonus for specific value alignments
        $valuesBonuses = [
            'family_oriented' => 15,
            'career_focused' => 10,
            'adventure_seeking' => 8,
            'creative' => 8,
            'stable_lifestyle' => 12
        ];

        $bonusScore = 0;
        foreach ($commonKeywords as $keyword) {
            $bonusScore += $valuesBonuses[$keyword] ?? 0;
        }

        return min(100, $overlapScore + ($bonusScore * 0.5));
    }

    private function calculateComplementarityBonus(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): float
    {
        $bonus = 0;

        // Beneficial complementarity in extraversion (research-backed)
        $extraversionDiff = abs($profile1->extraversion_score - $profile2->extraversion_score);
        if ($extraversionDiff > 20 && $extraversionDiff < 40) {
            $bonus += 15; // Moderate differences can be beneficial
        }

        // Leadership/support complementarity
        $conscientiousnessDiff = $profile1->conscientiousness_score - $profile2->conscientiousness_score;
        if (abs($conscientiousnessDiff) > 15 && abs($conscientiousnessDiff) < 30) {
            $bonus += 10; // One more organized partner can be helpful
        }

        // Creativity/stability balance
        $opennessDiff = abs($profile1->openness_score - $profile2->openness_score);
        if ($opennessDiff > 20 && $opennessDiff < 35) {
            $bonus += 8; // Creative/practical balance
        }

        return min(50, $bonus); // Cap complementarity bonus
    }

    private function applyResearchBasedAdjustments(float $baseScore, UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): float
    {
        $adjustedScore = $baseScore;

        // Neuroticism penalty (major research finding)
        $avgNeuroticism = ($profile1->neuroticism_score + $profile2->neuroticism_score) / 2;
        if ($avgNeuroticism > 70) {
            $adjustedScore -= 10; // High neuroticism reduces relationship satisfaction
        }

        // Secure attachment bonus
        if ($profile1->primary_attachment_style === 'secure' && $profile2->primary_attachment_style === 'secure') {
            $adjustedScore += 5; // Secure-secure is ideal
        }

        // Profile strength consideration
        $avgProfileStrength = ($profile1->profile_strength + $profile2->profile_strength) / 2;
        if ($avgProfileStrength < 0.6) {
            $adjustedScore *= 0.95; // Slight penalty for low-confidence profiles
        }

        // Age compatibility (general preference research)
        $ageDiff = abs($profile1->user->age ?? 25 - $profile2->user->age ?? 25);
        if ($ageDiff > 10) {
            $adjustedScore -= min(5, $ageDiff * 0.3);
        }

        return $adjustedScore;
    }

    private function identifyStrongestConnections(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): array
    {
        $connections = [];

        // Check trait similarities
        $traits = ['openness', 'conscientiousness', 'extraversion', 'agreeableness', 'neuroticism'];
        foreach ($traits as $trait) {
            $diff = abs($profile1->{$trait . '_score'} - $profile2->{$trait . '_score'});
            if ($diff < 15) {
                $connections[] = "Similar levels of " . ucfirst($trait);
            }
        }

        // Check attachment compatibility
        if ($profile1->primary_attachment_style === $profile2->primary_attachment_style) {
            $connections[] = "Shared " . $profile1->primary_attachment_style . " attachment style";
        }

        // Check keyword overlap
        $commonKeywords = array_intersect(
            $profile1->compatibility_keywords ?? [],
            $profile2->compatibility_keywords ?? []
        );
        if (count($commonKeywords) > 2) {
            $connections[] = "Common values: " . implode(', ', array_slice($commonKeywords, 0, 3));
        }

        return array_slice($connections, 0, 3); // Top 3 connections
    }

    private function identifyPotentialChallenges(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): array
    {
        $challenges = [];

        // High neuroticism warning
        if ($profile1->neuroticism_score > 70 || $profile2->neuroticism_score > 70) {
            $challenges[] = "May need extra emotional support during stress";
        }

        // Anxious-avoidant pairing
        if (($profile1->primary_attachment_style === 'anxious' && $profile2->primary_attachment_style === 'avoidant') ||
            ($profile1->primary_attachment_style === 'avoidant' && $profile2->primary_attachment_style === 'anxious')) {
            $challenges[] = "Different approaches to intimacy and independence";
        }

        // Extreme conscientiousness differences
        $conscientiousnessDiff = abs($profile1->conscientiousness_score - $profile2->conscientiousness_score);
        if ($conscientiousnessDiff > 40) {
            $challenges[] = "Different organizational and planning styles";
        }

        // Very low agreeableness
        if ($profile1->agreeableness_score < 30 || $profile2->agreeableness_score < 30) {
            $challenges[] = "May need to work on compromise and cooperation";
        }

        return array_slice($challenges, 0, 2); // Top 2 challenges
    }

    private function predictRelationshipStyle(UserPsychologicalProfile $profile1, UserPsychologicalProfile $profile2): string
    {
        $avgExtraversion = ($profile1->extraversion_score + $profile2->extraversion_score) / 2;
        $avgOpenness = ($profile1->openness_score + $profile2->openness_score) / 2;
        $avgConscientiousness = ($profile1->conscientiousness_score + $profile2->conscientiousness_score) / 2;

        if ($avgExtraversion > 70 && $avgOpenness > 60) {
            return "Social and adventurous partnership";
        }

        if ($avgConscientiousness > 70 && $profile1->primary_attachment_style === 'secure' && $profile2->primary_attachment_style === 'secure') {
            return "Stable and goal-oriented relationship";
        }

        if ($avgOpenness > 70) {
            return "Creative and intellectually stimulating bond";
        }

        if ($profile1->primary_attachment_style === 'secure' || $profile2->primary_attachment_style === 'secure') {
            return "Emotionally supportive and trusting connection";
        }

        return "Balanced and complementary partnership";
    }

    private function getUserProfile(int $userId): ?UserPsychologicalProfile
    {
        return Cache::remember("user_psychology_profile_{$userId}", 3600, function() use ($userId) {
            return UserPsychologicalProfile::where('user_id', $userId)
                ->where('is_active', true)
                ->latest()
                ->first();
        });
    }

    private function analyzeBehavioralPatterns(User $user): array
    {
        // This would analyze actual user behavior data
        // For now, return sample data structure
        return [
            'avg_response_time' => 5000, // milliseconds
            'activity_level' => 75, // 0-100 scale
            'text_patterns' => [
                'avg_message_length' => 45,
                'emoji_usage' => 0.3,
                'question_frequency' => 0.15
            ],
            'active_hours' => [18, 19, 20, 21, 22] // Hours of day when active
        ];
    }

    private function compareTextPatterns(array $patterns1, array $patterns2): float
    {
        $score = 100;

        // Compare message lengths
        $lengthDiff = abs($patterns1['avg_message_length'] - $patterns2['avg_message_length']);
        $score -= $lengthDiff * 0.5;

        // Compare emoji usage
        $emojiDiff = abs($patterns1['emoji_usage'] - $patterns2['emoji_usage']);
        $score -= $emojiDiff * 30;

        return max(0, min(100, $score));
    }

    private function calculateTimingOverlap(array $hours1, array $hours2): float
    {
        $overlap = array_intersect($hours1, $hours2);
        $union = array_unique(array_merge($hours1, $hours2));

        return count($union) > 0 ? (count($overlap) / count($union)) * 100 : 0;
    }
}
