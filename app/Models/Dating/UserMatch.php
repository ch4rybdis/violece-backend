<?php

namespace App\Models\Dating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class UserMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user1_id',
        'user2_id',
        'compatibility_score',
        'matched_at',
        'is_active',
        'match_context',
        'conversation_starter',
        'last_activity_at',
        'match_quality_score'
    ];

    protected $casts = [
        'matched_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_active' => 'boolean',
        'match_context' => 'array',
        'compatibility_score' => 'float',
        'match_quality_score' => 'float'
    ];

    /**
     * Relationship to first user
     */
    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    /**
     * Relationship to second user
     */
    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    /**
     * Get all messages for this match
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'match_id')->orderBy('created_at');
    }

    /**
     * Get the other user in this match (not the current user)
     */
    public function getOtherUser(int $userId): User
    {
        if ($this->user1_id === $userId) {
            return $this->user2;
        } elseif ($this->user2_id === $userId) {
            return $this->user1;
        }

        throw new \InvalidArgumentException("User ID {$userId} is not part of this match");
    }

    /**
     * Check if a user is part of this match
     */
    public function includesUser(int $userId): bool
    {
        return $this->user1_id === $userId || $this->user2_id === $userId;
    }

    /**
     * Scope for active matches only
     */
    public function scopeActiveMatches($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for matches involving a specific user
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user1_id', $userId)
                ->orWhere('user2_id', $userId);
        });
    }

    /**
     * Scope for recent matches
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('matched_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for high quality matches
     */
    public function scopeHighQuality($query, float $threshold = 70.0)
    {
        return $query->where('compatibility_score', '>=', $threshold);
    }

    /**
     * Get the conversation starter suggestion
     */
    public function getConversationStarter(): ?string
    {
        return $this->conversation_starter;
    }

    /**
     * Update match activity timestamp
     */
    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Deactivate the match
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'last_activity_at' => now()
        ]);
    }

    /**
     * Get match duration in days
     */
    public function getMatchDurationDays(): int
    {
        return $this->matched_at->diffInDays(now());
    }

    /**
     * Check if match has recent activity
     */
    public function hasRecentActivity(int $days = 3): bool
    {
        return $this->last_activity_at &&
            $this->last_activity_at->isAfter(now()->subDays($days));
    }

    /**
     * Get match statistics
     */
    public function getMatchStats(): array
    {
        $messageCount = $this->messages()->count();
        $lastMessage = $this->messages()->latest()->first();

        return [
            'message_count' => $messageCount,
            'last_message_at' => $lastMessage?->created_at,
            'match_duration_days' => $this->getMatchDurationDays(),
            'has_recent_activity' => $this->hasRecentActivity(),
            'compatibility_score' => $this->compatibility_score,
            'match_quality' => $this->getMatchQualityLabel()
        ];
    }

    /**
     * Get human-readable match quality label
     */
    public function getMatchQualityLabel(): string
    {
        $score = $this->compatibility_score;

        if ($score >= 85) return 'Exceptional';
        if ($score >= 75) return 'Excellent';
        if ($score >= 65) return 'Very Good';
        if ($score >= 55) return 'Good';
        if ($score >= 45) return 'Average';

        return 'Below Average';
    }

    /**
     * Generate conversation starters based on match context
     */
    public function generateConversationStarters(): array
    {
        $context = $this->match_context ?? [];
        $starters = [];

        // Psychology-based starters
        if (isset($context['shared_traits'])) {
            foreach ($context['shared_traits'] as $trait) {
                $starters[] = $this->getTraitBasedStarter($trait);
            }
        }

        // Interest-based starters
        if (isset($context['shared_interests'])) {
            foreach ($context['shared_interests'] as $interest) {
                $starters[] = "I noticed we both enjoy {$interest}. What got you into that?";
            }
        }

        // Default starters
        if (empty($starters)) {
            $starters = [
                "What's been the highlight of your week so far?",
                "I'm curious - what's something you're passionate about lately?",
                "What's your go-to way to unwind after a long day?",
                "If you could have dinner with anyone, dead or alive, who would it be?",
                "What's something you've learned recently that surprised you?"
            ];
        }

        return array_slice($starters, 0, 3); // Return top 3
    }

    /**
     * Get trait-based conversation starter
     */
    private function getTraitBasedStarter(string $trait): string
    {
        $traitStarters = [
            'openness' => "I see we both appreciate new experiences. What's the most interesting thing you've discovered recently?",
            'conscientiousness' => "I noticed we both value organization. Do you have any productivity tips that work well for you?",
            'extraversion' => "We both seem to enjoy social activities. What's your favorite way to spend time with friends?",
            'agreeableness' => "I can tell we both value harmony in relationships. What's most important to you in a friendship?",
            'neuroticism' => "We both understand life's ups and downs. What helps you stay grounded during challenging times?"
        ];

        return $traitStarters[$trait] ?? "I noticed we have some similar personality traits. What do you think makes for good compatibility?";
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        // Ensure user1_id is always smaller than user2_id (prevents duplicates)
        static::creating(function ($match) {
            if ($match->user1_id > $match->user2_id) {
                $temp = $match->user1_id;
                $match->user1_id = $match->user2_id;
                $match->user2_id = $temp;
            }
        });
    }
}
