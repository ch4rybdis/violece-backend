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
        'deletion_requested_at' => 'datetime',
        'password' => 'hashed',
        'is_premium' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Dating Relationships
    public function sentInteractions(): HasMany
    {
        return $this->hasMany(UserInteraction::class, 'user_id');
    }

    public function receivedInteractions(): HasMany
    {
        return $this->hasMany(UserInteraction::class, 'target_user_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(UserMatch::class, 'user1_id')
            ->orWhere('user2_id', $this->id);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    // Event Relationships
    public function eventParticipations(): HasMany
    {
        return $this->hasMany(EventParticipation::class);
    }

    // Violece Business Logic
    public function age(): int
    {
        return $this->birth_date->diffInYears(now());
    }

    public function isPremium(): bool
    {
        return $this->is_premium &&
            ($this->premium_expires_at === null || $this->premium_expires_at->isFuture());
    }

    public function isOnline(): bool
    {
        return $this->last_active_at &&
            $this->last_active_at->diffInMinutes(now()) <= 5;
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
