<?php

namespace App\Http\Resources\Api\Psychology;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform user psychological profiles for API responses
 * Comprehensive personality analysis data
 */
class ProfileResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user_id' => (int) $this->user_id,
            'profile_version' => $this->profile_version ?? '1.0',
            'completion_status' => [
                'is_complete' => (bool) $this->is_complete,
                'completion_percentage' => $this->calculateCompletionPercentage(),
                'completed_at' => $this->completed_at?->toISOString(),
                'last_updated' => $this->updated_at?->toISOString(),
            ],
            'big_five_traits' => [
                'openness' => [
                    'score' => round($this->openness_score ?? 0, 2),
                    'percentile' => $this->calculatePercentile('openness', $this->openness_score),
                    'level' => $this->getTraitLevel($this->openness_score),
                    'description' => $this->getTraitDescription('openness', $this->openness_score),
                ],
                'conscientiousness' => [
                    'score' => round($this->conscientiousness_score ?? 0, 2),
                    'percentile' => $this->calculatePercentile('conscientiousness', $this->conscientiousness_score),
                    'level' => $this->getTraitLevel($this->conscientiousness_score),
                    'description' => $this->getTraitDescription('conscientiousness', $this->conscientiousness_score),
                ],
                'extraversion' => [
                    'score' => round($this->extraversion_score ?? 0, 2),
                    'percentile' => $this->calculatePercentile('extraversion', $this->extraversion_score),
                    'level' => $this->getTraitLevel($this->extraversion_score),
                    'description' => $this->getTraitDescription('extraversion', $this->extraversion_score),
                ],
                'agreeableness' => [
                    'score' => round($this->agreeableness_score ?? 0, 2),
                    'percentile' => $this->calculatePercentile('agreeableness', $this->agreeableness_score),
                    'level' => $this->getTraitLevel($this->agreeableness_score),
                    'description' => $this->getTraitDescription('agreeableness', $this->agreeableness_score),
                ],
                'neuroticism' => [
                    'score' => round($this->neuroticism_score ?? 0, 2),
                    'percentile' => $this->calculatePercentile('neuroticism', $this->neuroticism_score),
                    'level' => $this->getTraitLevel($this->neuroticism_score),
                    'description' => $this->getTraitDescription('neuroticism', $this->neuroticism_score),
                ],
            ],
            'attachment_style' => [
                'primary_style' => $this->attachment_style,
                'secure_score' => round($this->secure_attachment_score ?? 0, 2),
                'anxious_score' => round($this->anxious_attachment_score ?? 0, 2),
                'avoidant_score' => round($this->avoidant_attachment_score ?? 0, 2),
                'description' => $this->getAttachmentDescription($this->attachment_style),
                'relationship_implications' => $this->getAttachmentImplications($this->attachment_style),
            ],
            'personality_summary' => [
                'dominant_traits' => $this->getDominantTraits(),
                'personality_type' => $this->getPersonalityType(),
                'strengths' => $this->getPersonalityStrengths(),
                'growth_areas' => $this->getGrowthAreas(),
                'ideal_partner_traits' => $this->getIdealPartnerTraits(),
            ],
            'compatibility_preferences' => [
                'preferred_traits' => json_decode($this->preferred_traits ?? '{}', true),
                'deal_breakers' => json_decode($this->deal_breakers ?? '[]', true),
                'flexibility_score' => round($this->flexibility_score ?? 5.0, 1),
            ],
            'behavioral_insights' => [
                'communication_style' => $this->getCommunicationStyle(),
                'conflict_resolution' => $this->getConflictResolutionStyle(),
                'emotional_expression' => $this->getEmotionalExpressionStyle(),
                'social_preferences' => $this->getSocialPreferences(),
            ],
            'metadata' => [
                'question_set_version' => $this->question_set_version,
                'total_responses' => $this->getTotalResponses(),
                'average_response_time' => round($this->average_response_time ?? 0, 2),
                'is_active' => (bool) $this->is_active,
                'privacy_level' => $this->privacy_level ?? 'standard',
            ],
        ];
    }

    private function calculateCompletionPercentage(): int
    {
        if ($this->is_complete) {
            return 100;
        }

        // Calculate based on filled fields
        $requiredFields = ['openness_score', 'conscientiousness_score', 'extraversion_score', 'agreeableness_score', 'neuroticism_score'];
        $filledFields = 0;

        foreach ($requiredFields as $field) {
            if (!is_null($this->$field)) {
                $filledFields++;
            }
        }

        return round($filledFields / count($requiredFields) * 100);
    }

    private function calculatePercentile(string $trait, ?float $score): int
    {
        if (is_null($score)) {
            return 50; // Default to median
        }

        // Simplified percentile calculation (in production, use actual population data)
        // Assuming scores are normalized to 0-10 scale
        return min(99, max(1, round($score * 10)));
    }

    private function getTraitLevel(?float $score): string
    {
        if (is_null($score)) {
            return 'unknown';
        }

        if ($score >= 7.5) return 'very_high';
        if ($score >= 6.0) return 'high';
        if ($score >= 4.0) return 'moderate';
        if ($score >= 2.5) return 'low';
        return 'very_low';
    }

    private function getTraitDescription(string $trait, ?float $score): string
    {
        if (is_null($score)) {
            return 'Not yet determined';
        }

        $level = $this->getTraitLevel($score);

        $descriptions = [
            'openness' => [
                'very_high' => 'Highly creative, curious, and open to new experiences',
                'high' => 'Creative and enjoys exploring new ideas and experiences',
                'moderate' => 'Balanced approach to new experiences and established routines',
                'low' => 'Prefers familiar experiences and established routines',
                'very_low' => 'Strongly prefers routine and conventional approaches',
            ],
            'conscientiousness' => [
                'very_high' => 'Extremely organized, disciplined, and goal-oriented',
                'high' => 'Well-organized and reliable with strong self-discipline',
                'moderate' => 'Generally organized with good follow-through on commitments',
                'low' => 'More spontaneous, may struggle with organization',
                'very_low' => 'Highly spontaneous, dislikes rigid structure',
            ],
            'extraversion' => [
                'very_high' => 'Highly social, energetic, and outgoing',
                'high' => 'Social and energetic, enjoys being around others',
                'moderate' => 'Comfortable in both social and solitary situations',
                'low' => 'Prefers smaller groups and quieter environments',
                'very_low' => 'Strongly prefers solitude and intimate settings',
            ],
            'agreeableness' => [
                'very_high' => 'Extremely cooperative, trusting, and empathetic',
                'high' => 'Cooperative and considerate of others\' needs',
                'moderate' => 'Generally cooperative while maintaining personal boundaries',
                'low' => 'More competitive and direct in communication',
                'very_low' => 'Highly competitive and skeptical of others\' motives',
            ],
            'neuroticism' => [
                'very_high' => 'Experiences frequent emotional ups and downs',
                'high' => 'More sensitive to stress and emotional changes',
                'moderate' => 'Generally emotionally stable with occasional sensitivity',
                'low' => 'Emotionally stable and resilient to stress',
                'very_low' => 'Extremely calm and emotionally stable',
            ],
        ];

        return $descriptions[$trait][$level] ?? 'No description available';
    }

    private function getAttachmentDescription(?string $attachmentStyle): string
    {
        $descriptions = [
            'secure' => 'Comfortable with intimacy and independence in relationships',
            'anxious' => 'Values close relationships but may worry about partner\'s feelings',
            'avoidant' => 'Values independence and may find intimacy challenging',
            'disorganized' => 'May have mixed feelings about closeness and independence',
        ];

        return $descriptions[$attachmentStyle] ?? 'Attachment style not determined';
    }

    private function getAttachmentImplications(?string $attachmentStyle): array
    {
        $implications = [
            'secure' => [
                'strengths' => ['Good communication', 'Comfortable with intimacy', 'Trusting'],
                'considerations' => ['May need partner who appreciates stability'],
            ],
            'anxious' => [
                'strengths' => ['Deeply caring', 'Committed to relationships', 'Emotionally expressive'],
                'considerations' => ['May need reassurance', 'Benefits from patient communication'],
            ],
            'avoidant' => [
                'strengths' => ['Independent', 'Self-reliant', 'Respects boundaries'],
                'considerations' => ['May need time to open up', 'Values personal space'],
            ],
        ];

        return $implications[$attachmentStyle] ?? [
            'strengths' => [],
            'considerations' => ['Attachment style still being determined'],
        ];
    }

    private function getDominantTraits(): array
    {
        $traits = [
            'openness' => $this->openness_score ?? 0,
            'conscientiousness' => $this->conscientiousness_score ?? 0,
            'extraversion' => $this->extraversion_score ?? 0,
            'agreeableness' => $this->agreeableness_score ?? 0,
            'neuroticism' => $this->neuroticism_score ?? 0,
        ];

        arsort($traits);
        return array_slice(array_keys($traits), 0, 2);
    }

    private function getPersonalityType(): string
    {
        // Simplified personality typing based on dominant traits
        $dominant = $this->getDominantTraits();

        if (in_array('extraversion', $dominant) && in_array('openness', $dominant)) {
            return 'The Enthusiast';
        }
        if (in_array('conscientiousness', $dominant) && in_array('agreeableness', $dominant)) {
            return 'The Supporter';
        }
        if (in_array('openness', $dominant) && in_array('conscientiousness', $dominant)) {
            return 'The Innovator';
        }

        return 'The Individualist';
    }

    private function getPersonalityStrengths(): array
    {
        $strengths = [];

        if (($this->openness_score ?? 0) >= 6) {
            $strengths[] = 'Creative and open-minded';
        }
        if (($this->conscientiousness_score ?? 0) >= 6) {
            $strengths[] = 'Reliable and organized';
        }
        if (($this->extraversion_score ?? 0) >= 6) {
            $strengths[] = 'Social and energetic';
        }
        if (($this->agreeableness_score ?? 0) >= 6) {
            $strengths[] = 'Cooperative and empathetic';
        }
        if (($this->neuroticism_score ?? 0) <= 4) {
            $strengths[] = 'Emotionally stable';
        }

        return $strengths ?: ['Unique personality blend'];
    }

    private function getGrowthAreas(): array
    {
        $growthAreas = [];

        if (($this->conscientiousness_score ?? 5) < 4) {
            $growthAreas[] = 'Organization and planning';
        }
        if (($this->agreeableness_score ?? 5) < 4) {
            $growthAreas[] = 'Collaborative communication';
        }
        if (($this->neuroticism_score ?? 5) > 6) {
            $growthAreas[] = 'Stress management';
        }

        return $growthAreas ?: ['Continue developing all areas'];
    }

    private function getIdealPartnerTraits(): array
    {
        // Based on compatibility research and current user's traits
        $ideal = [];

        if (($this->extraversion_score ?? 5) > 6) {
            $ideal[] = 'Social compatibility';
        } else {
            $ideal[] = 'Appreciates quiet time';
        }

        if (($this->openness_score ?? 5) > 6) {
            $ideal[] = 'Open to new experiences';
        }

        if (($this->conscientiousness_score ?? 5) > 6) {
            $ideal[] = 'Shares life goals';
        }

        return $ideal ?: ['Complementary personality'];
    }

    private function getCommunicationStyle(): string
    {
        $extraversion = $this->extraversion_score ?? 5;
        $agreeableness = $this->agreeableness_score ?? 5;

        if ($extraversion > 6 && $agreeableness > 6) {
            return 'Warm and expressive';
        } elseif ($extraversion > 6) {
            return 'Direct and energetic';
        } elseif ($agreeableness > 6) {
            return 'Thoughtful and considerate';
        }

        return 'Balanced and authentic';
    }

    private function getConflictResolutionStyle(): string
    {
        $agreeableness = $this->agreeableness_score ?? 5;
        $neuroticism = $this->neuroticism_score ?? 5;

        if ($agreeableness > 6 && $neuroticism < 4) {
            return 'Collaborative problem-solver';
        } elseif ($agreeableness > 6) {
            return 'Seeks harmony and understanding';
        }

        return 'Direct but fair approach';
    }

    private function getEmotionalExpressionStyle(): string
    {
        $extraversion = $this->extraversion_score ?? 5;
        $neuroticism = $this->neuroticism_score ?? 5;

        if ($extraversion > 6 && $neuroticism < 4) {
            return 'Open and stable';
        } elseif ($extraversion > 6) {
            return 'Expressive and emotional';
        }

        return 'Thoughtful and measured';
    }

    private function getSocialPreferences(): array
    {
        $extraversion = $this->extraversion_score ?? 5;
        $openness = $this->openness_score ?? 5;

        $preferences = [];

        if ($extraversion > 6) {
            $preferences[] = 'Group activities';
            $preferences[] = 'Meeting new people';
        } else {
            $preferences[] = 'Intimate gatherings';
            $preferences[] = 'Deep one-on-one conversations';
        }

        if ($openness > 6) {
            $preferences[] = 'Trying new experiences';
        } else {
            $preferences[] = 'Familiar comfortable settings';
        }

        return $preferences;
    }

    private function getTotalResponses(): int
    {
        // This would typically come from a relationship or calculation
        return $this->total_responses ?? 0;
    }
}
