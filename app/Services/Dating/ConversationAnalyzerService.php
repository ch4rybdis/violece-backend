<?php

namespace App\Services\Dating;

use App\Models\Dating\Message;
use App\Models\Dating\UserMatch;
use Illuminate\Support\Collection;

class ConversationAnalyzerService
{
    /**
     * Analyze if a conversation is ready for a date suggestion
     */
    public function shouldSuggestDate(UserMatch $match): bool
    {
        // Check message frequency (3+ messages in 10 minutes)
        $recentMessages = Message::where('match_id', $match->id)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->get();

        if ($recentMessages->count() < 3) {
            return false;
        }

        // Check if conversation has positive sentiment
        $sentiment = $this->analyzeSentiment($recentMessages);
        if ($sentiment < 0.6) { // Threshold for positive conversation
            return false;
        }

        // Check if both users are active
        if (!$match->user1->isOnline() || !$match->user2->isOnline()) {
            return false;
        }

        // Check geographic proximity
        $distance = $this->calculateDistance($match->user1, $match->user2);
        if ($distance > 30) { // 30km threshold
            return false;
        }

        // Check if date was recently suggested (avoid repetition)
        $recentSuggestion = $match->dateSuggestions()
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if ($recentSuggestion) {
            return false;
        }

        return true;
    }

    /**
     * Analyze sentiment of conversation
     */
    private function analyzeSentiment(Collection $messages): float
    {
        // Simple implementation - count positive words/emojis
        $positivePatterns = [
            '/\b(love|like|enjoy|fun|happy|excited|great|good|yes|sure|definitely)\b/i',
            '/[😊😃😄😁🙂😍🥰👍]/u'
        ];

        $totalMessages = $messages->count();
        if ($totalMessages === 0) return 0;

        $positiveMessages = 0;

        foreach ($messages as $message) {
            foreach ($positivePatterns as $pattern) {
                if (preg_match($pattern, $message->message_text)) {
                    $positiveMessages++;
                    break; // Count each message only once
                }
            }
        }

        return $positiveMessages / $totalMessages;
    }

    /**
     * Calculate distance between users
     */
    private function calculateDistance($user1, $user2): ?float
    {
        if (!$user1->location || !$user2->location) {
            return null;
        }

        // Use existing distance calculation from your MatchingController
        // This should use PostGIS ST_Distance

        return 5.0; // Placeholder - use your actual implementation
    }
}
