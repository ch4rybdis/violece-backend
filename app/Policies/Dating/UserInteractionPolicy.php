<?php

namespace App\Policies\Dating;

use App\Models\User;
use App\Models\Dating\UserInteraction;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Authorization policy for user interactions (swipe actions)
 * Ensures users can only perform allowed interactions
 */
class UserInteractionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user can view their own interactions
     */
    public function viewOwn(User $user): bool
    {
        return $user->is_active && $user->hasCompletedOnboarding();
    }

    /**
     * Determine if user can like another user
     */
    public function like(User $user, User $targetUser): bool
    {
        return $this->canInteractWith($user, $targetUser) &&
            $this->hasRemainingLikes($user);
    }

    /**
     * Determine if user can pass on another user
     */
    public function pass(User $user, User $targetUser): bool
    {
        return $this->canInteractWith($user, $targetUser);
    }

    /**
     * Determine if user can super like another user
     */
    public function superLike(User $user, User $targetUser): bool
    {
        return $this->canInteractWith($user, $targetUser) &&
            $this->hasRemainingSuperLikes($user);
    }

    /**
     * Determine if user can undo their last interaction
     */
    public function undo(User $user): bool
    {
        if (!$user->hasActiveSubscription()) {
            return false; // Undo is premium feature
        }

        // Check if there's a recent interaction to undo (within last 10 minutes)
        $recentInteraction = UserInteraction::where('user_id', $user->id)
            ->where('created_at', '>', now()->subMinutes(10))
            ->where('is_undone', false)
            ->latest()
            ->first();

        return $recentInteraction !== null;
    }

    /**
     * Determine if user can boost their profile
     */
    public function boost(User $user): bool
    {
        if (!$user->hasActiveSubscription()) {
            return false; // Boost is premium feature
        }

        // Check daily boost limit
        $todayBoosts = $user->boosts()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $dailyBoostLimit = $user->getSubscriptionFeature('daily_boosts', 1);

        return $todayBoosts < $dailyBoostLimit;
    }

    /**
     * Determine if user can view who liked them
     */
    public function viewLikes(User $user): bool
    {
        // Free users can see limited likes, premium users see all
        return $user->is_active && $user->hasCompletedOnboarding();
    }

    /**
     * Determine if user can see read receipts
     */
    public function viewReadReceipts(User $user): bool
    {
        return $user->hasActiveSubscription(); // Premium feature
    }

    /**
     * Base validation for user interactions
     */
    private function canInteractWith(User $user, User $targetUser): bool
    {
        // Basic eligibility checks
        if (!$user->is_active || !$targetUser->is_active) {
            return false;
        }

        // Both users must have completed onboarding
        if (!$user->hasCompletedOnboarding() || !$targetUser->hasCompletedOnboarding()) {
            return false;
        }

        // Cannot interact with self
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Check if already interacted
        if ($this->hasAlreadyInteracted($user, $targetUser)) {
            return false;
        }

        // Check if target user has blocked current user
        if ($targetUser->hasBlocked($user)) {
            return false;
        }

        // Check if current user has blocked target user
        if ($user->hasBlocked($targetUser)) {
            return false;
        }

        // Check age compatibility
        if (!$this->areAgeCompatible($user, $targetUser)) {
            return false;
        }

        // Check distance limits
        if (!$this->areWithinDistanceRange($user, $targetUser)) {
            return false;
        }

        return true;
    }

    /**
     * Check if user has remaining daily likes
     */
    private function hasRemainingLikes(User $user): bool
    {
        $todayLikes = UserInteraction::where('user_id', $user->id)
            ->whereIn('interaction_type', ['like', 'super_like'])
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $dailyLikeLimit = $user->hasActiveSubscription()
            ? PHP_INT_MAX
            : config('violece.free_tier.daily_likes', 20);

        return $todayLikes < $dailyLikeLimit;
    }

    /**
     * Check if user has remaining daily super likes
     */
    private function hasRemainingSuperLikes(User $user): bool
    {
        $todaySuperLikes = UserInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'super_like')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $dailySuperLikeLimit = $user->hasActiveSubscription() ? 5 : 1;

        return $todaySuperLikes < $dailySuperLikeLimit;
    }

    /**
     * Check if users have already interacted
     */
    private function hasAlreadyInteracted(User $user, User $targetUser): bool
    {
        return UserInteraction::where('user_id', $user->id)
            ->where('target_user_id', $targetUser->id)
            ->where('is_undone', false)
            ->exists();
    }

    /**
     * Check if users are age compatible based on preferences
     */
    private function areAgeCompatible(User $user, User $targetUser): bool
    {
        if (!$user->date_of_birth || !$targetUser->date_of_birth) {
            return true; // Skip check if birth dates not available
        }

        $userAge = $user->date_of_birth->age;
        $targetAge = $targetUser->date_of_birth->age;

        // Check user's age preferences
        $userPreferences = $user->preferences;
        if ($userPreferences) {
            $minAge = $userPreferences->min_age ?? ($userAge - 10);
            $maxAge = $userPreferences->max_age ?? ($userAge + 10);

            if ($targetAge < $minAge || $targetAge > $maxAge) {
                return false;
            }
        }

        // Check target user's age preferences
        $targetPreferences = $targetUser->preferences;
        if ($targetPreferences) {
            $targetMinAge = $targetPreferences->min_age ?? ($targetAge - 10);
            $targetMaxAge = $targetPreferences->max_age ?? ($targetAge + 10);

            if ($userAge < $targetMinAge || $userAge > $targetMaxAge) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if users are within distance range
     */
    private function areWithinDistanceRange(User $user, User $targetUser): bool
    {
        if (!$user->location || !$targetUser->location) {
            return true; // Skip check if locations not available
        }

        $distance = $user->location->distanceTo($targetUser->location);

        // Check user's distance preference
        $userPreferences = $user->preferences;
        $maxDistance = $userPreferences?->max_distance ?? 50; // Default 50km

        return $distance <= $maxDistance;
    }

    /**
     * Check if user can report another user
     */
    public function report(User $user, User $targetUser): bool
    {
        // Can't report self
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Check if already reported recently
        $recentReport = \App\Models\UserReport::where('reporter_id', $user->id)
            ->where('reported_user_id', $targetUser->id)
            ->where('created_at', '>', now()->subDays(7))
            ->exists();

        return !$recentReport;
    }

    /**
     * Check if user can block another user
     */
    public function block(User $user, User $targetUser): bool
    {
        return $user->id !== $targetUser->id && !$user->hasBlocked($targetUser);
    }

    /**
     * Check if user can unblock another user
     */
    public function unblock(User $user, User $targetUser): bool
    {
        return $user->hasBlocked($targetUser);
    }

    /**
     * Check if user can view interaction analytics
     */
    public function viewAnalytics(User $user): bool
    {
        return $user->hasActiveSubscription(); // Premium feature
    }

    /**
     * Check if user can export their interaction data
     */
    public function exportData(User $user): bool
    {
        return $user->is_active; // GDPR compliance - all users can export
    }

    /**
     * Check if user can delete their interaction history
     */
    public function deleteHistory(User $user): bool
    {
        return $user->is_active; // GDPR compliance - all users can delete
    }
}
