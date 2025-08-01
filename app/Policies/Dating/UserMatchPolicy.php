<?php

namespace App\Policies\Dating;

use App\Models\User;
use App\Models\Dating\UserMatch;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorization policy for user matches
 * Ensures users can only access their own matches and match-related data
 */
class UserMatchPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view the match
     */
    public function view(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match) &&
            $match->status === 'active';
    }

    /**
     * Determine if user can view all their matches
     */
    public function viewOwn(User $user): bool
    {
        return $user->is_active && $user->hasCompletedOnboarding();
    }

    /**
     * Determine if user can unmatch
     */
    public function unmatch(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match) &&
            $match->status === 'active';
    }

    /**
     * Determine if user can send messages in this match
     */
    public function message(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        if ($match->status !== 'active') {
            return false;
        }

        // Check if either user has blocked the other
        $otherUser = $this->getOtherUser($user, $match);
        if ($user->hasBlocked($otherUser) || $otherUser->hasBlocked($user)) {
            return false;
        }

        return true;
    }

    /**
     * Determine if user can view message history
     */
    public function viewMessages(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match);
    }

    /**
     * Determine if user can report the match/other user
     */
    public function report(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        $otherUser = $this->getOtherUser($user, $match);

        // Check if already reported recently
        $recentReport = \App\Models\UserReport::where('reporter_id', $user->id)
            ->where('reported_user_id', $otherUser->id)
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        return !$recentReport;
    }

    /**
     * Determine if user can block the other user in the match
     */
    public function block(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        $otherUser = $this->getOtherUser($user, $match);
        return !$user->hasBlocked($otherUser);
    }

    /**
     * Determine if user can view compatibility analysis
     */
    public function viewCompatibility(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match);
    }

    /**
     * Determine if user can view detailed match insights
     */
    public function viewInsights(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match) &&
            $user->hasActiveSubscription(); // Premium feature
    }

    /**
     * Determine if user can get date suggestions for this match
     */
    public function getDateSuggestions(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        if ($match->status !== 'active') {
            return false;
        }

        // Require some conversation before suggesting dates
        $messageCount = $match->messages()->count();
        return $messageCount >= 5; // At least 5 messages exchanged
    }

    /**
     * Determine if user can share contact information
     */
    public function shareContact(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        if ($match->status !== 'active') {
            return false;
        }

        // Require established conversation
        $messageCount = $match->messages()->count();
        $matchAge = $match->created_at->diffInHours();

        return $messageCount >= 10 && $matchAge >= 2; // 10+ messages and 2+ hours old
    }

    /**
     * Determine if user can archive the match
     */
    public function archive(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match);
    }

    /**
     * Determine if user can restore archived match
     */
    public function restore(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match) &&
            $match->status === 'archived';
    }

    /**
     * Determine if user can delete match history
     */
    public function delete(User $user, UserMatch $match): bool
    {
        return $this->isParticipantInMatch($user, $match);
    }

    /**
     * Determine if user can view match statistics
     */
    public function viewStats(User $user): bool
    {
        return $user->hasActiveSubscription(); // Premium feature
    }

    /**
     * Determine if user can export match data
     */
    public function exportData(User $user): bool
    {
        return $user->is_active; // GDPR compliance
    }

    /**
     * Determine if user can video call in this match
     */
    public function videoCall(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        if ($match->status !== 'active') {
            return false;
        }

        if (!$user->hasActiveSubscription()) {
            return false; // Premium feature
        }

        // Require established conversation and mutual consent
        $messageCount = $match->messages()->count();
        $matchAge = $match->created_at->diffInDays();

        return $messageCount >= 15 && $matchAge >= 1; // 15+ messages and 1+ day old
    }

    /**
     * Determine if user can send gifts in this match
     */
    public function sendGift(User $user, UserMatch $match): bool
    {
        if (!$this->isParticipantInMatch($user, $match)) {
            return false;
        }

        if ($match->status !== 'active') {
            return false;
        }

        // Check daily gift limit
        $todayGifts = $match->gifts()
            ->where('sender_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $dailyGiftLimit = $user->hasActiveSubscription() ? 5 : 1;

        return $todayGifts < $dailyGiftLimit;
    }

    /**
     * Check if user is a participant in the match
     */
    private function isParticipantInMatch(User $user, UserMatch $match): bool
    {
        return $match->user1_id === $user->id || $match->user2_id === $user->id;
    }

    /**
     * Get the other user in the match
     */
    private function getOtherUser(User $user, UserMatch $match): User
    {
        if ($match->user1_id === $user->id) {
            return $match->user2;
        }

        return $match->user1;
    }

    /**
     * Before policy checks - ensure match exists and is accessible
     */
    public function before(User $user, $ability): ?bool
    {
        // Admin users can do anything
        if ($user->hasRole('admin')) {
            return true;
        }

        // Suspended users cannot access matches
        if ($user->is_suspended) {
            return false;
        }

        return null; // Continue with normal policy checks
    }
}
