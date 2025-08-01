<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;



use App\Models\Dating\{UserInteraction,  Message};
use App\Models\Events\EventParticipation;
use App\Models\Dating\UserMatch;



class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'uuid',
        'email',
        'phone',
        'password',
        'first_name',
        'birth_date',
        'gender',
        'preference_gender',
        'location',
        'location_updated_at',
        'max_distance',
        'psychological_traits',
        'questionnaire_responses',
        'compatibility_vector',
        'profile_photos',
        'bio',
        'interests',
        'profile_completion_score',
        'last_active_at',
        'is_premium',
        'premium_expires_at',
        'is_verified',
        'is_active',
    ];




    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'uuid' => 'string',
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'preference_gender' => 'array',
        'location_updated_at' => 'datetime',
        'psychological_traits' => 'array',
        'questionnaire_responses' => 'array',
        'compatibility_vector' => 'array',
        'profile_photos' => 'array',
        'interests' => 'array',
        'profile_completion_score' => 'float',
        'last_active_at' => 'datetime',
        'premium_expires_at' => 'datetime',
        'password' => 'hashed',
        'is_premium' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'phone_verified_at' => 'datetime',
        'preference_age_min' => 'integer',
        'preference_age_max' => 'integer',
        'total_swipes' => 'integer',
        'total_matches' => 'integer',
        'response_time_avg' => 'integer',
        'deletion_requested_at' => 'datetime',
    ];


    /**
     * Get all matches for this user (as user1 or user2)
     */
    public function matches(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserMatch::class, 'user1_id')
            ->orWhere('user2_id', $this->id);
    }

    /**
     * Get active matches only
     */
    public function activeMatches()
    {
        return UserMatch::forUser($this->id)->activeMatches();
    }

    /**
     * Get interactions made by this user
     */
    public function interactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserInteraction::class, 'user_id');
    }

    /**
     * Get interactions targeting this user
     */
    public function receivedInteractions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserInteraction::class, 'target_user_id');
    }

    /**
     * Get messages sent by this user
     */
    public function sentMessages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Check if user is premium
     */
    public function isPremium(): bool
    {
        return $this->is_premium && $this->premium_expires_at && $this->premium_expires_at->isFuture();
    }


    /**
     * Check if user is online (last seen within 15 minutes)
     */
    public function isOnline(): bool
    {
        return $this->last_active_at && $this->last_active_at->isAfter(now()->subMinutes(15));
    }

    /**
     * Get user's age from birth_date
     */
    public function age(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }


    /**
     * Check if user has liked another user
     */
    public function hasLiked(int $targetUserId): bool
    {
        return $this->interactions()
            ->where('target_user_id', $targetUserId)
            ->whereIn('interaction_type', [UserInteraction::TYPE_LIKE, UserInteraction::TYPE_SUPER_LIKE])
            ->exists();
    }

    /**
     * Check if user has interacted with another user
     */
    public function hasInteractedWith(int $targetUserId): bool
    {
        return $this->interactions()
            ->where('target_user_id', $targetUserId)
            ->exists();
    }

    /**
     * Check if users are matched
     */
    public function isMatchedWith(int $otherUserId): bool
    {
        return UserMatch::where(function($query) use ($otherUserId) {
            $query->where('user1_id', $this->id)->where('user2_id', $otherUserId);
        })->orWhere(function($query) use ($otherUserId) {
            $query->where('user1_id', $otherUserId)->where('user2_id', $this->id);
        })->where('is_active', true)->exists();
    }

    /**
     * Get match with another user
     */
    public function getMatchWith(int $otherUserId): ?UserMatch
    {
        return UserMatch::where(function($query) use ($otherUserId) {
            $query->where('user1_id', $this->id)->where('user2_id', $otherUserId);
        })->orWhere(function($query) use ($otherUserId) {
            $query->where('user1_id', $otherUserId)->where('user2_id', $this->id);
        })->where('is_active', true)->first();
    }

    /**
     * Get daily interaction statistics
     */
    public function getDailyInteractionStats(): array
    {
        return UserInteraction::getDailyLimits($this);
    }

    /**
     * Get profile completion percentage
     */
    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            'first_name', 'birth_date', 'gender', 'bio',
            'location', 'preference_gender', 'preference_age_min', 'preference_age_max'
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $completed++;
            }
        }

        // Check psychological profile
        if ($this->psychologicalProfile && $this->psychologicalProfile->is_active) {
            $completed++;
        }

        // Check photos
        if ($this->profile_photos && count($this->profile_photos) > 0) {
            $completed++;
        }

        $total = count($fields) + 2; // +2 for psychology and photos
        return round(($completed / $total) * 100);
    }

    /**
     * Update last seen timestamp
     */
    public function updateLastSeen(): void
    {
        $this->update(['last_active_at' => now()]);
    }


    /**
     * Get user's photo URLs (with fallback)
     */
    public function getProfilePhotosAttribute(): array
    {
        $photos = $this->attributes['profile_photos'] ?? '[]';
        $photoArray = is_string($photos) ? json_decode($photos, true) : $photos;

        if (empty($photoArray)) {
            return ['/images/default-avatar.jpg']; // Default avatar
        }

        return $photoArray;
    }

    /**
     * Scope for active users only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for users with complete psychological profiles
     */
    public function scopeWithPsychologyProfile($query)
    {
        return $query->whereHas('psychologicalProfile', function($q) {
            $q->where('is_active', true);
        });
    }

    public function psychologicalProfile()
    {
        return $this->hasOne(\App\Models\Psychology\UserPsychologicalProfile::class, 'user_id');
    }


    /**
     * Scope for users within distance range
     */
    public function scopeWithinDistance($query, $latitude, $longitude, $distanceKm)
    {
        return $query->whereRaw(
            "ST_DWithin(location, ST_MakePoint(?, ?), ?)",
            [$longitude, $latitude, $distanceKm * 1000] // Convert to meters
        );
    }

    /**
     * Scope for users of specific gender
     */
    public function scopeOfGender($query, string $gender)
    {
        return $query->where('gender', $gender);
    }

    /**
     * Scope for users in age range
     */
    public function scopeInAgeRange($query, int $minAge, int $maxAge)
    {
        return $query->whereRaw('EXTRACT(YEAR FROM AGE(birth_date)) BETWEEN ? AND ?', [$minAge, $maxAge]);
    }


    // Dating Relationships
    public function sentInteractions(): HasMany
    {
        return $this->hasMany(UserInteraction::class, 'user_id');
    }



    // Event Relationships
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(EventParticipation::class);
    }





    public function getCompatibilityWith(User $user): float
    {
        // Violece compatibility algorithm placeholder
        return 0.0;
    }

    public function getDistanceFrom(User $user): float
    {
        // PostGIS distance calculation placeholder
        return 0.0;
    }

    public function canSwipeMore(): bool
    {
        if ($this->isPremium()) return true;

        $todaySwipes = $this->sentInteractions()
            ->whereDate('created_at', today())
            ->count();

        return $todaySwipes < 20; // Free tier limit
    }
}
