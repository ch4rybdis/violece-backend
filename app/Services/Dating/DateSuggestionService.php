<?php

namespace App\Services\Dating;

use App\Models\Dating\UserMatch;
use App\Models\Dating\DateSuggestion;
use App\Models\User;

class DateSuggestionService
{
    /**
     * Generate a date suggestion for a match
     */
    public function generateSuggestion(UserMatch $match): DateSuggestion
    {
        $user1 = $match->user1;
        $user2 = $match->user2;

        $userPreferences = $this->combineUserPreferences($user1, $user2);
        $locationData = $this->getSharedLocation($user1, $user2);
        $timeAvailability = $this->estimateTimeAvailability($user1, $user2);

        $activity = $this->selectActivity($userPreferences, $locationData);
        $venue = $this->findVenue($locationData, $activity['type']);
        $timing = $this->suggestTiming($timeAvailability);

        // Create suggestion record
        return DateSuggestion::create([
            'match_id' => $match->id,
            'activity_type' => $activity['type'],
            'activity_name' => $activity['name'],
            'activity_description' => $activity['description'],
            'venue_name' => $venue['name'] ?? null,
            'venue_address' => $venue['address'] ?? null,
            'venue_latitude' => $venue['latitude'] ?? null,
            'venue_longitude' => $venue['longitude'] ?? null,
            'suggested_day' => $timing['day'],
            'suggested_time' => $timing['time'],
            'compatibility_reason' => $this->generateCompatibilityReason($user1, $user2, $activity),
            'created_at' => now(),
        ]);
    }

    /**
     * Combine user preferences based on psychological profiles
     */
    private function combineUserPreferences(User $user1, User $user2): array
    {
        $profile1 = $user1->psychologicalProfile;
        $profile2 = $user2->psychologicalProfile;

        // Extract relevant traits
        $extraversion = ($profile1->extraversion_score + $profile2->extraversion_score) / 2;
        $openness = ($profile1->openness_score + $profile2->openness_score) / 2;

        // Determine activity preferences
        $activityTypes = [];

        // High extraversion = social activities
        if ($extraversion > 70) {
            $activityTypes[] = 'social';
            $activityTypes[] = 'entertainment';
        }
        // Low extraversion = intimate settings
        elseif ($extraversion < 40) {
            $activityTypes[] = 'intimate';
            $activityTypes[] = 'quiet';
        }
        // Moderate = balanced activities
        else {
            $activityTypes[] = 'balanced';
        }

        // High openness = creative, novel experiences
        if ($openness > 70) {
            $activityTypes[] = 'creative';
            $activityTypes[] = 'cultural';
        }

        // Get shared interests
        $interests1 = $user1->interests ?? [];
        $interests2 = $user2->interests ?? [];
        $sharedInterests = array_values(array_intersect($interests1, $interests2));

        return [
            'activity_types' => $activityTypes,
            'shared_interests' => $sharedInterests,
            'extraversion_level' => $extraversion,
            'openness_level' => $openness,
        ];
    }

    /**
     * Get shared location between users
     */
    private function getSharedLocation(User $user1, User $user2): array
    {
        // Calculate midpoint between users if both have locations
        if ($user1->location && $user2->location) {
            // Extract coordinates (using your existing location structure)
            $lat1 = $user1->latitude;
            $lng1 = $user1->longitude;
            $lat2 = $user2->latitude;
            $lng2 = $user2->longitude;

            // Simple midpoint calculation
            $midLat = ($lat1 + $lat2) / 2;
            $midLng = ($lng1 + $lng2) / 2;

            return [
                'latitude' => $midLat,
                'longitude' => $midLng,
                'city' => $user1->city ?? $user2->city ?? null,
                'country' => $user1->country ?? $user2->country ?? null,
            ];
        }

        // Fall back to available location
        if ($user1->location) {
            return [
                'latitude' => $user1->latitude,
                'longitude' => $user1->longitude,
                'city' => $user1->city,
                'country' => $user1->country,
            ];
        }

        if ($user2->location) {
            return [
                'latitude' => $user2->latitude,
                'longitude' => $user2->longitude,
                'city' => $user2->city,
                'country' => $user2->country,
            ];
        }

        // No location data available
        return [
            'latitude' => null,
            'longitude' => null,
            'city' => null,
            'country' => null,
        ];
    }

    /**
     * Estimate time availability based on user activity patterns
     */
    private function estimateTimeAvailability(User $user1, User $user2): array
    {
        // Check recent activity patterns to estimate availability
        // For now, use simple heuristics based on current time

        $now = now();
        $dayOfWeek = $now->dayOfWeek;

        // Default to weekend evening if during week
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
            $suggestedDay = min(6, $dayOfWeek + 2); // Next weekend
            $suggestedTime = '19:00'; // 7 PM
        } else {
            // If weekend, suggest tomorrow
            $suggestedDay = $dayOfWeek == 6 ? 0 : $dayOfWeek + 1;
            $suggestedTime = '15:00'; // 3 PM
        }

        return [
            'day' => $suggestedDay,
            'time' => $suggestedTime,
        ];
    }

    /**
     * Select appropriate activity based on user preferences
     */
    private function selectActivity(array $preferences, array $location): array
    {
        $activityTypes = $preferences['activity_types'];
        $sharedInterests = $preferences['shared_interests'];

        // Define activity database - in production this would come from a database
        $activities = [
            // Social activities
            ['type' => 'social', 'name' => 'Cocktail Bar', 'description' => 'Enjoy creative drinks in a social atmosphere'],
            ['type' => 'social', 'name' => 'Board Game Cafe', 'description' => 'Fun games and friendly competition'],
            ['type' => 'social', 'name' => 'Cooking Class', 'description' => 'Learn to make a new dish together'],

            // Intimate activities
            ['type' => 'intimate', 'name' => 'Cozy Cafe', 'description' => 'Quiet conversation over coffee'],
            ['type' => 'intimate', 'name' => 'Botanical Garden', 'description' => 'A peaceful stroll among beautiful plants'],
            ['type' => 'intimate', 'name' => 'Wine Tasting', 'description' => 'Sample different wines and discuss preferences'],

            // Creative activities
            ['type' => 'creative', 'name' => 'Art Gallery', 'description' => 'Explore artistic perspectives together'],
            ['type' => 'creative', 'name' => 'Pottery Class', 'description' => 'Create something with your hands'],
            ['type' => 'creative', 'name' => 'Street Art Tour', 'description' => 'Discover local artistic expressions'],

            // Cultural activities
            ['type' => 'cultural', 'name' => 'Museum Visit', 'description' => 'Explore history and culture together'],
            ['type' => 'cultural', 'name' => 'Food Market', 'description' => 'Sample local and international cuisines'],
            ['type' => 'cultural', 'name' => 'Live Music', 'description' => 'Enjoy performances by local artists'],

            // Entertainment
            ['type' => 'entertainment', 'name' => 'Escape Room', 'description' => 'Solve puzzles and challenges together'],
            ['type' => 'entertainment', 'name' => 'Arcade', 'description' => 'Classic and modern games for friendly competition'],
            ['type' => 'entertainment', 'name' => 'Comedy Show', 'description' => 'Share laughs and lighthearted fun'],

            // Quiet activities
            ['type' => 'quiet', 'name' => 'Bookstore Browse', 'description' => 'Discover each other\'s literary tastes'],
            ['type' => 'quiet', 'name' => 'Scenic Picnic', 'description' => 'Relaxed conversation in a beautiful setting'],
            ['type' => 'quiet', 'name' => 'Stargazing', 'description' => 'Contemplative evening under the stars'],

            // Balanced activities
            ['type' => 'balanced', 'name' => 'Farmers Market', 'description' => 'Browse local produce and artisanal goods'],
            ['type' => 'balanced', 'name' => 'Walking Tour', 'description' => 'Explore interesting neighborhoods together'],
            ['type' => 'balanced', 'name' => 'Casual Dinner', 'description' => 'Relaxed conversation over a good meal'],
        ];

        // Filter activities by preference
        $filteredActivities = array_filter($activities, function($activity) use ($activityTypes) {
            return in_array($activity['type'], $activityTypes);
        });

        // If we have shared interests, try to match them
        if (!empty($sharedInterests)) {
            $interestKeywords = [
                'music' => ['Live Music', 'Concert', 'Jazz Bar'],
                'food' => ['Cooking Class', 'Food Market', 'Casual Dinner'],
                'art' => ['Art Gallery', 'Museum Visit', 'Pottery Class'],
                'books' => ['Bookstore Browse', 'Poetry Reading', 'Literary Cafe'],
                'outdoors' => ['Hiking Trail', 'Botanical Garden', 'Scenic Picnic'],
                'sports' => ['Rock Climbing', 'Tennis Match', 'Bowling'],
                'gaming' => ['Board Game Cafe', 'Arcade', 'Escape Room'],
                'movies' => ['Independent Cinema', 'Film Festival', 'Drive-In Movie'],
            ];

            foreach ($sharedInterests as $interest) {
                if (isset($interestKeywords[$interest])) {
                    // Add interest-specific activities
                    foreach ($interestKeywords[$interest] as $activityName) {
                        $filteredActivities[] = [
                            'type' => 'interest_' . $interest,
                            'name' => $activityName,
                            'description' => 'Based on your shared interest in ' . $interest
                        ];
                    }
                }
            }
        }

        // If no matches, use balanced activities
        if (empty($filteredActivities)) {
            $filteredActivities = array_filter($activities, function($activity) {
                return $activity['type'] === 'balanced';
            });
        }

        // Select random activity from filtered list
        return $filteredActivities[array_rand($filteredActivities)];
    }

    /**
     * Find venue for selected activity
     */
    private function findVenue(array $location, string $activityType): array
    {
        // In a production app, this would integrate with a venue API like Google Places
        // For now, return placeholder data
        return [
            'name' => 'Le Petit Café',
            'address' => '123 Main Street',
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'rating' => 4.5,
            'price_level' => 2,
        ];
    }

    /**
     * Suggest optimal timing
     */
    private function suggestTiming(array $availability): array
    {
        // For now, just return the estimated availability
        return $availability;
    }

    /**
     * Generate compatibility reason for suggestion
     */
    private function generateCompatibilityReason(User $user1, User $user2, array $activity): string
    {
        $reasons = [
            "Based on your shared personality traits, you might enjoy {$activity['name']} together.",
            "Your conversation suggests you'd have a great time at {$activity['name']}.",
            "Given your interests, {$activity['name']} could be a perfect date.",
            "Your compatibility score suggests {$activity['name']} would be a great experience for you both."
        ];

        return $reasons[array_rand($reasons)];
    }
}
