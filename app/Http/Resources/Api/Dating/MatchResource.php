<?php

namespace App\Http\Resources\Api\Dating;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform match data and compatibility analysis for API responses
 * Comprehensive compatibility scoring and insights
 */
class MatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'match_id' => (int) $this->id,
            'users' => [
                'current_user' => [
                    'id' => (int) $this->user1_id,
                    'name' => $this->user1->first_name ?? 'Unknown',
                    'age' => $this->calculateAge($this->user1->date_of_birth),
                    'primary_photo' => $this->user1->getPrimaryPhotoUrl(),
                ],
                'matched_user' => [
                    'id' => (int) $this->user2_id,
                    'name' => $this->user2->first_name ?? 'Unknown',
                    'age' => $this->calculateAge($this->user2->date_of_birth),
                    'primary_photo' => $this->user2->getPrimaryPhotoUrl(),
                    'distance' => $this->formatDistance($this->distance_km),
                ],
            ],
            'compatibility_analysis' => [
                'total_score' => (int) round($this->compatibility_score ?? 0),
                'score_tier' => $this->getScoreTier($this->compatibility_score),
                'component_scores' => [
                    'personality_similarity' => [
                        'score' => round($this->personality_score ?? 0, 1),
                        'weight' => 40,
                        'breakdown' => $this->getPersonalityBreakdown(),
                    ],
                    'attachment_compatibility' => [
                        'score' => round($this->attachment_score ?? 0, 1),
                        'weight' => 25,
                        'analysis' => $this->getAttachmentAnalysis(),
                    ],
                    'behavioral_patterns' => [
                        'score' => round($this->behavioral_score ?? 0, 1),
                        'weight' => 20,
                        'insights' => $this->getBehavioralInsights(),
                    ],
                    'values_alignment' => [
                        'score' => round($this->values_score ?? 0, 1),
                        'weight' => 10,
                        'shared_values' => $this->getSharedValues(),
                    ],
                    'complementarity_bonus' => [
                        'score' => round($this->complementarity_score ?? 0, 1),
                        'weight' => 5,
                        'beneficial_differences' => $this->getBeneficialDifferences(),
                    ],
                ],
                'detailed_analysis' => [
                    'strongest_connections' => $this->getStrongestConnections(),
                    'potential_challenges' => $this->getPotentialChallenges(),
                    'relationship_style_prediction' => $this->getRelationshipStylePrediction(),
                    'communication_compatibility' => $this->getCommunicationCompatibility(),
                    'long_term_potential' => $this->getLongTermPotential(),
                ],
            ],
            'match_reasons' => [
                'primary_reasons' => $this->getPrimaryMatchReasons(),
                'unique_qualities' => $this->getUniqueQualities(),
                'shared_interests' => $this->getSharedInterests(),
                'growth_opportunities' => $this->getGrowthOpportunities(),
            ],
            'conversation_starters' => [
                'personality_based' => $this->getPersonalityBasedStarters(),
                'interest_based' => $this->getInterestBasedStarters(),
                'values_based' => $this->getValuesBasedStarters(),
            ],
            'date_suggestions' => [
                'activity_type' => $this->getSuggestedActivityType(),
                'specific_ideas' => $this->getSpecificDateIdeas(),
                'timing_preferences' => $this->getTimingPreferences(),
                'environment' => $this->getPreferredEnvironment(),
            ],
            'relationship_insights' => [
                'predicted_dynamic' => $this->getPredictedDynamic(),
                'success_factors' => $this->getSuccessFactors(),
                'areas_to_nurture' => $this->getAreasToNurture(),
                'timeline_expectations' => $this->getTimelineExpectations(),
            ],
            'metadata' => [
                'matched_at' => $this->created_at?->toISOString(),
                'algorithm_version' => $this->algorithm_version ?? '1.0',
                'match_type' => $this->match_type ?? 'standard',
                'is_super_like' => (bool) $this->is_super_like,
                'mutual_friends' => $this->getMutualFriendsCount(),
                'shared_locations' => $this->getSharedLocations(),
            ],
        ];
    }

    private function calculateAge($dateOfBirth): ?int
    {
        if (!$dateOfBirth) {
            return null;
        }

        return now()->diffInYears($dateOfBirth);
    }

    private function formatDistance(?float $distanceKm): string
    {
        if (is_null($distanceKm)) {
            return 'Distance unknown';
        }

        if ($distanceKm < 1) {
            return 'Less than 1 km away';
        } elseif ($distanceKm < 10) {
            return round($distanceKm, 1) . ' km away';
        } else {
            return round($distanceKm) . ' km away';
        }
    }

    private function getScoreTier(?float $score): string
    {
        if (is_null($score)) {
            return 'unrated';
        }

        if ($score >= 90) return 'exceptional';
        if ($score >= 80) return 'excellent';
        if ($score >= 70) return 'very_good';
        if ($score >= 60) return 'good';
        if ($score >= 50) return 'moderate';
        return 'low';
    }

    private function getPersonalityBreakdown(): array
    {
        // Get personality profiles for both users
        $user1Profile = $this->user1->psychologicalProfile;
        $user2Profile = $this->user2->psychologicalProfile;

        if (!$user1Profile || !$user2Profile) {
            return ['analysis' => 'Personality data incomplete'];
        }

        return [
            'big_five_similarity' => [
                'openness' => $this->calculateTraitSimilarity($user1Profile->openness_score, $user2Profile->openness_score),
                'conscientiousness' => $this->calculateTraitSimilarity($user1Profile->conscientiousness_score, $user2Profile->conscientiousness_score),
                'extraversion' => $this->calculateTraitSimilarity($user1Profile->extraversion_score, $user2Profile->extraversion_score),
                'agreeableness' => $this->calculateTraitSimilarity($user1Profile->agreeableness_score, $user2Profile->agreeableness_score),
                'neuroticism' => $this->calculateTraitSimilarity($user1Profile->neuroticism_score, $user2Profile->neuroticism_score),
            ],
            'dominant_shared_traits' => $this->getDominantSharedTraits($user1Profile, $user2Profile),
            'complementary_traits' => $this->getComplementaryTraits($user1Profile, $user2Profile),
        ];
    }

    private function calculateTraitSimilarity(?float $score1, ?float $score2): array
    {
        if (is_null($score1) || is_null($score2)) {
            return ['similarity' => 0, 'analysis' => 'Data incomplete'];
        }

        $difference = abs($score1 - $score2);
        $similarity = max(0, 100 - ($difference * 10)); // Convert to percentage

        $analysis = 'Very different';
        if ($similarity >= 80) $analysis = 'Very similar';
        elseif ($similarity >= 60) $analysis = 'Similar';
        elseif ($similarity >= 40) $analysis = 'Somewhat different';

        return [
            'similarity' => round($similarity),
            'analysis' => $analysis,
            'user1_score' => round($score1, 1),
            'user2_score' => round($score2, 1),
        ];
    }

    private function getDominantSharedTraits($profile1, $profile2): array
    {
        $traits1 = [
            'openness' => $profile1->openness_score ?? 0,
            'conscientiousness' => $profile1->conscientiousness_score ?? 0,
            'extraversion' => $profile1->extraversion_score ?? 0,
            'agreeableness' => $profile1->agreeableness_score ?? 0,
        ];

        $traits2 = [
            'openness' => $profile2->openness_score ?? 0,
            'conscientiousness' => $profile2->conscientiousness_score ?? 0,
            'extraversion' => $profile2->extraversion_score ?? 0,
            'agreeableness' => $profile2->agreeableness_score ?? 0,
        ];

        $sharedTraits = [];
        foreach ($traits1 as $trait => $score1) {
            $score2 = $traits2[$trait];
            if ($score1 >= 6 && $score2 >= 6) { // Both high in this trait
                $sharedTraits[] = $trait;
            }
        }

        return $sharedTraits;
    }

    private function getComplementaryTraits($profile1, $profile2): array
    {
        // Find beneficial complementary differences
        $complementary = [];

        // High conscientiousness + moderate neuroticism can be balancing
        if (($profile1->conscientiousness_score ?? 0) >= 7 && ($profile2->neuroticism_score ?? 0) <= 4) {
            $complementary[] = 'Stability and organization balance';
        }

        // High openness + high conscientiousness
        if (($profile1->openness_score ?? 0) >= 6 && ($profile2->conscientiousness_score ?? 0) >= 6) {
            $complementary[] = 'Creativity meets reliability';
        }

        return $complementary;
    }

    private function getAttachmentAnalysis(): array
    {
        $user1Attachment = $this->user1->psychologicalProfile?->attachment_style;
        $user2Attachment = $this->user2->psychologicalProfile?->attachment_style;

        if (!$user1Attachment || !$user2Attachment) {
            return ['analysis' => 'Attachment data incomplete'];
        }

        $compatibility = $this->calculateAttachmentCompatibility($user1Attachment, $user2Attachment);

        return [
            'user1_style' => $user1Attachment,
            'user2_style' => $user2Attachment,
            'compatibility_rating' => $compatibility['rating'],
            'analysis' => $compatibility['analysis'],
            'relationship_dynamics' => $compatibility['dynamics'],
        ];
    }

    private function calculateAttachmentCompatibility(string $style1, string $style2): array
    {
        $compatibilityMatrix = [
            'secure' => [
                'secure' => ['rating' => 95, 'analysis' => 'Excellent compatibility', 'dynamics' => 'Stable and trusting relationship'],
                'anxious' => ['rating' => 85, 'analysis' => 'Good compatibility', 'dynamics' => 'Secure partner provides stability'],
                'avoidant' => ['rating' => 75, 'analysis' => 'Moderate compatibility', 'dynamics' => 'May help avoidant partner open up'],
            ],
            'anxious' => [
                'secure' => ['rating' => 85, 'analysis' => 'Good compatibility', 'dynamics' => 'Secure partner provides reassurance'],
                'anxious' => ['rating' => 60, 'analysis' => 'Challenging but possible', 'dynamics' => 'May amplify insecurities'],
                'avoidant' => ['rating' => 45, 'analysis' => 'Difficult combination', 'dynamics' => 'Push-pull dynamic likely'],
            ],
            'avoidant' => [
                'secure' => ['rating' => 75, 'analysis' => 'Moderate compatibility', 'dynamics' => 'Secure partner respects boundaries'],
                'anxious' => ['rating' => 45, 'analysis' => 'Difficult combination', 'dynamics' => 'Conflicting intimacy needs'],
                'avoidant' => ['rating' => 70, 'analysis' => 'Understands independence needs', 'dynamics' => 'May lack emotional intimacy'],
            ],
        ];

        return $compatibilityMatrix[$style1][$style2] ?? [
            'rating' => 50,
            'analysis' => 'Unknown compatibility',
            'dynamics' => 'Relationship dynamics unclear'
        ];
    }

    private function getBehavioralInsights(): array
    {
        return [
            'communication_styles' => $this->analyzeCommunicationStyles(),
            'social_preferences' => $this->analyzeSocialPreferences(),
            'lifestyle_compatibility' => $this->analyzeLifestyleCompatibility(),
        ];
    }

    private function analyzeCommunicationStyles(): string
    {
        $user1Extraversion = $this->user1->psychologicalProfile?->extraversion_score ?? 5;
        $user2Extraversion = $this->user2->psychologicalProfile?->extraversion_score ?? 5;

        if (abs($user1Extraversion - $user2Extraversion) <= 2) {
            return 'Similar communication energy levels';
        } elseif ($user1Extraversion > 6 && $user2Extraversion < 4) {
            return 'Complementary - one more expressive, one more reflective';
        }

        return 'Different communication styles that may require understanding';
    }

    private function analyzeSocialPreferences(): string
    {
        $user1Extraversion = $this->user1->psychologicalProfile?->extraversion_score ?? 5;
        $user2Extraversion = $this->user2->psychologicalProfile?->extraversion_score ?? 5;

        if ($user1Extraversion >= 6 && $user2Extraversion >= 6) {
            return 'Both enjoy social activities and meeting new people';
        } elseif ($user1Extraversion <= 4 && $user2Extraversion <= 4) {
            return 'Both prefer intimate settings and quiet time together';
        }

        return 'Balanced social preferences - variety in activities';
    }

    private function analyzeLifestyleCompatibility(): string
    {
        $user1Conscientiousness = $this->user1->psychologicalProfile?->conscientiousness_score ?? 5;
        $user2Conscientiousness = $this->user2->psychologicalProfile?->conscientiousness_score ?? 5;

        if (abs($user1Conscientiousness - $user2Conscientiousness) <= 2) {
            return 'Similar approaches to planning and organization';
        }

        return 'Different organizational styles that can be complementary';
    }

    private function getSharedValues(): array
    {
        // This would typically come from user preferences or questionnaire responses
        return [
            'family_orientation' => 'Both value close relationships',
            'career_ambition' => 'Supportive of professional goals',
            'lifestyle_preferences' => 'Compatible life rhythms',
        ];
    }

    private function getBeneficialDifferences(): array
    {
        return [
            'skill_complementarity' => 'Different strengths that support each other',
            'perspective_diversity' => 'Varied viewpoints enrich relationship',
            'growth_opportunities' => 'Learn and grow together',
        ];
    }

    private function getStrongestConnections(): array
    {
        $connections = [];

        // Analyze personality compatibility
        $personalityBreakdown = $this->getPersonalityBreakdown();
        if (isset($personalityBreakdown['dominant_shared_traits'])) {
            foreach ($personalityBreakdown['dominant_shared_traits'] as $trait) {
                $connections[] = "Shared strength in " . ucfirst($trait);
            }
        }

        // Add attachment compatibility if high
        $attachmentAnalysis = $this->getAttachmentAnalysis();
        if (isset($attachmentAnalysis['compatibility_rating']) && $attachmentAnalysis['compatibility_rating'] >= 80) {
            $connections[] = "Strong attachment compatibility";
        }

        return array_slice($connections, 0, 3); // Top 3 connections
    }

    private function getPotentialChallenges(): array
    {
        $challenges = [];

        // Check for potential personality conflicts
        $user1Profile = $this->user1->psychologicalProfile;
        $user2Profile = $this->user2->psychologicalProfile;

        if ($user1Profile && $user2Profile) {
            // High neuroticism in both could be challenging
            if (($user1Profile->neuroticism_score ?? 0) > 6 && ($user2Profile->neuroticism_score ?? 0) > 6) {
                $challenges[] = "Both partners may be sensitive to stress";
            }

            // Very different conscientiousness levels
            $conscientiousDiff = abs(($user1Profile->conscientiousness_score ?? 5) - ($user2Profile->conscientiousness_score ?? 5));
            if ($conscientiousDiff > 4) {
                $challenges[] = "Different approaches to organization and planning";
            }
        }

        return array_slice($challenges, 0, 2); // Top 2 challenges
    }

    private function getRelationshipStylePrediction(): string
    {
        $user1Attachment = $this->user1->psychologicalProfile?->attachment_style;
        $user2Attachment = $this->user2->psychologicalProfile?->attachment_style;

        if ($user1Attachment === 'secure' && $user2Attachment === 'secure') {
            return 'Stable, trusting relationship with open communication';
        } elseif (in_array('secure', [$user1Attachment, $user2Attachment])) {
            return 'Secure partner likely to provide stability and reassurance';
        }

        return 'Relationship will require understanding and patience';
    }

    private function getCommunicationCompatibility(): array
    {
        return [
            'style_match' => $this->analyzeCommunicationStyles(),
            'conflict_resolution' => 'Both likely to approach conflicts constructively',
            'emotional_expression' => 'Compatible ways of sharing feelings',
        ];
    }

    private function getLongTermPotential(): string
    {
        $score = $this->compatibility_score ?? 0;

        if ($score >= 85) {
            return 'Excellent long-term potential with strong foundations';
        } elseif ($score >= 70) {
            return 'Good long-term potential with mutual effort';
        } elseif ($score >= 55) {
            return 'Moderate potential requiring understanding and compromise';
        }

        return 'Challenging but not impossible with significant work';
    }

    private function getPrimaryMatchReasons(): array
    {
        $reasons = [];

        // High compatibility score
        if (($this->compatibility_score ?? 0) >= 80) {
            $reasons[] = 'Exceptional personality compatibility';
        }

        // Shared dominant traits
        $personalityBreakdown = $this->getPersonalityBreakdown();
        if (!empty($personalityBreakdown['dominant_shared_traits'])) {
            $reasons[] = 'Strong shared personality traits';
        }

        // Good attachment compatibility
        $attachmentAnalysis = $this->getAttachmentAnalysis();
        if (isset($attachmentAnalysis['compatibility_rating']) && $attachmentAnalysis['compatibility_rating'] >= 75) {
            $reasons[] = 'Compatible attachment styles';
        }

        return array_slice($reasons, 0, 3);
    }

    private function getUniqueQualities(): array
    {
        // This would come from user profiles and preferences
        return [
            'Complementary strengths that enhance each other',
            'Shared values with refreshing different perspectives',
            'Similar life goals with unique approaches',
        ];
    }

    private function getSharedInterests(): array
    {
        // This would typically come from user interest data
        return [
            'Creative pursuits',
            'Active lifestyle',
            'Intellectual conversations',
        ];
    }

    private function getGrowthOpportunities(): array
    {
        return [
            'Learning from each other\'s strengths',
            'Balancing different approaches to life',
            'Supporting each other\'s personal development',
        ];
    }

    private function getPersonalityBasedStarters(): array
    {
        $starters = [];

        $sharedTraits = $this->getPersonalityBreakdown()['dominant_shared_traits'] ?? [];

        if (in_array('openness', $sharedTraits)) {
            $starters[] = "What's the most interesting thing you've learned recently?";
        }

        if (in_array('extraversion', $sharedTraits)) {
            $starters[] = "What's your ideal way to spend a weekend with friends?";
        }

        return array_slice($starters, 0, 2);
    }

    private function getInterestBasedStarters(): array
    {
        return [
            "I noticed we both seem to enjoy creative activities - what's your latest project?",
            "Do you have any favorite local spots you'd recommend?",
        ];
    }

    private function getValuesBasedStarters(): array
    {
        return [
            "What's something you're passionate about that might surprise people?",
            "How do you like to spend your free time to recharge?",
        ];
    }

    private function getSuggestedActivityType(): string
    {
        $user1Extraversion = $this->user1->psychologicalProfile?->extraversion_score ?? 5;
        $user2Extraversion = $this->user2->psychologicalProfile?->extraversion_score ?? 5;

        $avgExtraversion = ($user1Extraversion + $user2Extraversion) / 2;

        if ($avgExtraversion >= 6) {
            return 'social_active';
        } elseif ($avgExtraversion <= 4) {
            return 'intimate_quiet';
        }

        return 'balanced_variety';
    }

    private function getSpecificDateIdeas(): array
    {
        $activityType = $this->getSuggestedActivityType();

        $ideas = [
            'social_active' => ['Live music venue', 'Food festival', 'Group hiking'],
            'intimate_quiet' => ['Art gallery', 'Coffee shop', 'Bookstore browsing'],
            'balanced_variety' => ['Museum', 'Farmer\'s market', 'Scenic walk'],
        ];

        return $ideas[$activityType] ?? $ideas['balanced_variety'];
    }

    private function getTimingPreferences(): string
    {
        return 'Flexible timing based on mutual schedules';
    }

    private function getPreferredEnvironment(): string
    {
        $activityType = $this->getSuggestedActivityType();

        $environments = [
            'social_active' => 'Vibrant, energetic environments',
            'intimate_quiet' => 'Quiet, comfortable settings',
            'balanced_variety' => 'Varied environments depending on mood',
        ];

        return $environments[$activityType] ?? 'Comfortable, welcoming spaces';
    }

    private function getPredictedDynamic(): string
    {
        $attachmentAnalysis = $this->getAttachmentAnalysis();
        return $attachmentAnalysis['relationship_dynamics'] ?? 'Balanced partnership with mutual support';
    }

    private function getSuccessFactors(): array
    {
        return [
            'Open communication about needs and boundaries',
            'Mutual respect for individual differences',
            'Shared commitment to relationship growth',
        ];
    }

    private function getAreasToNurture(): array
    {
        $challenges = $this->getPotentialChallenges();

        if (empty($challenges)) {
            return ['Continue building trust and intimacy'];
        }

        return array_map(function($challenge) {
            return 'Address: ' . $challenge;
        }, $challenges);
    }

    private function getTimelineExpectations(): string
    {
        $score = $this->compatibility_score ?? 0;

        if ($score >= 80) {
            return 'Strong foundation suggests positive trajectory';
        } elseif ($score >= 60) {
            return 'Allow time for deeper connection to develop';
        }

        return 'Take time to understand each other\'s needs';
    }

    private function getMutualFriendsCount(): int
    {
        // This would typically come from social connections data
        return 0; // Placeholder
    }

    private function getSharedLocations(): array
    {
        // This would come from location/activity data
        return []; // Placeholder
    }
}
