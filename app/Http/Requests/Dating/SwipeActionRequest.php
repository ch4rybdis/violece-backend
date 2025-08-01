<?php

namespace App\Http\Requests\Dating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Dating\UserInteraction;

/**
 * Base class for swipe action validation
 * Shared logic for Like, Pass, and SuperLike requests
 */
abstract class SwipeActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->validateUserCanSwipe();
    }

    /**
     * Common validation rules for all swipe actions
     */
    protected function commonRules(): array
    {
        return [
            'target_user_id' => [
                'sometimes', // Can be in URL parameter instead
                'integer',
                'exists:users,id',
                'different:' . auth()->id(), // Prevent self-swiping
            ],
            'location' => 'sometimes|array',
            'location.latitude' => 'required_with:location|numeric|between:-90,90',
            'location.longitude' => 'required_with:location|numeric|between:-180,180',
            'source' => 'sometimes|string|in:discovery,boost,super_boost,rewind',
            'context' => 'sometimes|array',
            'context.session_id' => 'sometimes|string|max:255',
            'context.swipe_sequence' => 'sometimes|integer|min:1',
        ];
    }

    /**
     * Validate user can perform swipe actions
     */
    private function validateUserCanSwipe(): bool
    {
        $user = auth()->user();

        // Check if user account is active
        if (!$user || !$user->is_active) {
            return false;
        }

        // Check if user has completed onboarding
        if (!$user->profile_completed_at) {
            return false;
        }

        // Check if user has completed psychological assessment
        $profile = $user->psychologicalProfile;
        if (!$profile || !$profile->is_complete) {
            return false;
        }

        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateTargetUser($validator);
            $this->validateInteractionLimits($validator);
            $this->validateNoDuplicateInteraction($validator);
        });
    }

    /**
     * Validate target user is eligible for interaction
     */
    private function validateTargetUser($validator): void
    {
        $targetUserId = $this->getTargetUserId();
        if (!$targetUserId) {
            return;
        }

        $targetUser = User::find($targetUserId);
        if (!$targetUser) {
            $validator->errors()->add('target_user_id', 'Target user not found.');
            return;
        }

        // Check if target user is active
        if (!$targetUser->is_active) {
            $validator->errors()->add('target_user_id', 'Target user is not available.');
        }

        // Check if target user has completed profile
        if (!$targetUser->profile_completed_at) {
            $validator->errors()->add('target_user_id', 'Target user profile is incomplete.');
        }

        // Check if users are compatible age-wise (basic filter)
        $currentUser = auth()->user();
        if (!$this->areUsersAgeCompatible($currentUser, $targetUser)) {
            $validator->errors()->add('target_user_id', 'Users are not within compatible age range.');
        }
    }

    /**
     * Validate daily interaction limits
     */
    private function validateInteractionLimits($validator): void
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        // Get today's interaction count
        $todayInteractions = UserInteraction::where('user_id', $user->id)
            ->where('created_at', '>=', $today)
            ->count();

        // Check limits based on user subscription
        $dailyLimit = $this->getDailySwipeLimit($user);

        if ($todayInteractions >= $dailyLimit) {
            $validator->errors()->add(
                'daily_limit',
                "Daily swipe limit reached ({$dailyLimit}). Upgrade to premium for unlimited swipes."
            );
        }
    }

    /**
     * Validate no duplicate interaction exists
     */
    private function validateNoDuplicateInteraction($validator): void
    {
        $targetUserId = $this->getTargetUserId();
        if (!$targetUserId) {
            return;
        }

        $existingInteraction = UserInteraction::where('user_id', auth()->id())
            ->where('target_user_id', $targetUserId)
            ->first();

        if ($existingInteraction) {
            $validator->errors()->add(
                'target_user_id',
                'You have already interacted with this user.'
            );
        }
    }

    /**
     * Get target user ID from request or route parameter
     */
    protected function getTargetUserId(): ?int
    {
        return $this->input('target_user_id') ?? $this->route('userId');
    }

    /**
     * Check if users are within compatible age range
     */
    private function areUsersAgeCompatible(User $user1, User $user2): bool
    {
        if (!$user1->date_of_birth || !$user2->date_of_birth) {
            return true; // Skip check if birth dates not available
        }

        $age1 = $user1->date_of_birth->age;
        $age2 = $user2->date_of_birth->age;

        // Basic compatibility: within 15 years of each other
        return abs($age1 - $age2) <= 15;
    }

    /**
     * Get daily swipe limit based on user subscription
     */
    private function getDailySwipeLimit(User $user): int
    {
        // Check if user has premium subscription
        if ($user->hasActiveSubscription()) {
            return PHP_INT_MAX; // Unlimited for premium users
        }

        // Free tier limits
        return config('violece.free_tier.daily_swipes', 20);
    }

    /**
     * Get interaction metadata for analytics
     */
    public function getInteractionMetadata(): array
    {
        return [
            'source' => $this->input('source', 'discovery'),
            'location' => $this->input('location'),
            'context' => $this->input('context', []),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ];
    }
}

/**
 * Validation for Like interactions
 */
class LikeUserRequest extends SwipeActionRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'message' => 'sometimes|string|max:500|min:1',
            'is_super_like' => 'sometimes|boolean',
        ]);
    }

    public function messages(): array
    {
        return [
            'target_user_id.different' => 'You cannot like yourself.',
            'target_user_id.exists' => 'The selected user does not exist.',
            'message.max' => 'Like message cannot exceed 500 characters.',
            'message.min' => 'Like message cannot be empty.',
            'location.latitude.between' => 'Invalid latitude provided.',
            'location.longitude.between' => 'Invalid longitude provided.',
        ];
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            $this->validateSuperLikeEligibility($validator);
        });
    }

    /**
     * Validate super like eligibility and limits
     */
    private function validateSuperLikeEligibility($validator): void
    {
        if (!$this->input('is_super_like', false)) {
            return;
        }

        $user = auth()->user();
        $today = now()->startOfDay();

        // Check daily super like limit
        $todaySuperLikes = UserInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'super_like')
            ->where('created_at', '>=', $today)
            ->count();

        $dailySuperLikeLimit = $user->hasActiveSubscription() ? 5 : 1;

        if ($todaySuperLikes >= $dailySuperLikeLimit) {
            $validator->errors()->add(
                'is_super_like',
                "Daily Super Like limit reached ({$dailySuperLikeLimit})."
            );
        }
    }
}

/**
 * Validation for Pass interactions
 */
class PassUserRequest extends SwipeActionRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'reason' => 'sometimes|string|in:age,distance,lifestyle,photos,other',
            'hide_forever' => 'sometimes|boolean',
        ]);
    }

    public function messages(): array
    {
        return [
            'target_user_id.different' => 'Invalid user selection.',
            'target_user_id.exists' => 'The selected user does not exist.',
            'reason.in' => 'Invalid pass reason provided.',
        ];
    }
}

/**
 * Validation for Super Like interactions
 */
class SuperLikeUserRequest extends SwipeActionRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'message' => 'required|string|max:200|min:5',
            'highlight_trait' => 'sometimes|string|in:openness,conscientiousness,extraversion,agreeableness,neuroticism',
        ]);
    }

    public function withValidator($validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator) {
            $this->validateSuperLikeAvailability($validator);
            $this->validateSuperLikeQuality($validator);
        });
    }

    /**
     * Validate super like availability and limits
     */
    private function validateSuperLikeAvailability($validator): void
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        // Check daily super like limit
        $todaySuperLikes = UserInteraction::where('user_id', $user->id)
            ->where('interaction_type', 'super_like')
            ->where('created_at', '>=', $today)
            ->count();

        $dailyLimit = $user->hasActiveSubscription() ? 5 : 1;

        if ($todaySuperLikes >= $dailyLimit) {
            $validator->errors()->add(
                'super_like',
                "Daily Super Like limit reached ({$dailyLimit}). " .
                ($user->hasActiveSubscription() ? '' : 'Upgrade to premium for more Super Likes.')
            );
        }
    }

    /**
     * Validate super like message quality
     */
    private function validateSuperLikeQuality($validator): void
    {
        $message = $this->input('message', '');

        // Check for generic/spam messages
        $spamPatterns = [
            '/^(hi|hey|hello)$/i',
            '/^(what\'?s up|sup)$/i',
            '/^(how are you)$/i',
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, trim($message))) {
                $validator->errors()->add(
                    'message',
                    'Super Like messages should be personalized and thoughtful.'
                );
                break;
            }
        }

        // Check for appropriate content
        if ($this->containsInappropriateContent($message)) {
            $validator->errors()->add(
                'message',
                'Super Like message contains inappropriate content.'
            );
        }
    }

    /**
     * Basic inappropriate content detection
     */
    private function containsInappropriateContent(string $message): bool
    {
        $inappropriateWords = [
            'sex', 'sexy', 'hot', 'hookup', 'dtf', 'netflix and chill'
        ];

        $messageLower = strtolower($message);

        foreach ($inappropriateWords as $word) {
            if (str_contains($messageLower, $word)) {
                return true;
            }
        }

        return false;
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Super Likes require a personalized message.',
            'message.min' => 'Super Like message must be at least 5 characters.',
            'message.max' => 'Super Like message cannot exceed 200 characters.',
            'highlight_trait.in' => 'Invalid personality trait selected.',
        ];
    }
}
