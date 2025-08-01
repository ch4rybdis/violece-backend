<?php

namespace App\Models\Dating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class UserInteraction extends Model
{
    use HasFactory;

    // Interaction types
    const TYPE_LIKE = 1;
    const TYPE_PASS = 2;
    const TYPE_SUPER_LIKE = 3;
    const TYPE_BLOCK = 4;
    const TYPE_REPORT = 5;


    protected $fillable = [
        'user_id',
        'target_user_id',
        'interaction_type',
        'interaction_context',
        'is_mutual',
        'processed_at'
    ];

    protected $casts = [
        'interaction_context' => 'array',
        'is_mutual' => 'boolean',
        'processed_at' => 'datetime'
    ];

    /**
     * User who performed the interaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Target user of the interaction
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Scope for specific interaction type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope for likes only
     */
    public function scopeLikes($query)
    {
        return $query->where('interaction_type', self::TYPE_LIKE);
    }

    /**
     * Scope for super likes only
     */
    public function scopeSuperLikes($query)
    {
        return $query->where('interaction_type', self::TYPE_SUPER_LIKE);
    }

    /**
     * Scope for passes only
     */
    public function scopePasses($query)
    {
        return $query->where('interaction_type', self::TYPE_PASS);
    }

    /**
     * Scope for blocks only
     */
    public function scopeBlocks($query)
    {
        return $query->where('interaction_type', self::TYPE_BLOCK);
    }

    /**
     * Scope for mutual interactions
     */
    public function scopeMutual($query)
    {
        return $query->where('is_mutual', true);
    }

    /**
     * Scope for recent interactions
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for interactions by specific user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for interactions targeting specific user
     */
    public function scopeTargeting($query, int $targetUserId)
    {
        return $query->where('target_user_id', $targetUserId);
    }

    /**
     * Check if this is a positive interaction (like or super like)
     */
    public function isPositive(): bool
    {
        return in_array($this->interaction_type, [self::TYPE_LIKE, self::TYPE_SUPER_LIKE]);
    }

    /**
     * Check if this is a negative interaction (pass, block, report)
     */
    public function isNegative(): bool
    {
        return in_array($this->interaction_type, [self::TYPE_PASS, self::TYPE_BLOCK, self::TYPE_REPORT]);
    }

    /**
     * Check if this is a premium interaction (super like)
     */
    public function isPremium(): bool
    {
        return $this->interaction_type === self::TYPE_SUPER_LIKE;
    }

    /**
     * Mark interaction as processed
     */
    public function markAsProcessed(): void
    {
        $this->update(['processed_at' => now()]);
    }

    /**
     * Check for mutual interaction
     */
    public function checkMutualInteraction(): bool
    {
        // Look for reciprocal interaction
        $mutual = self::where('user_id', $this->target_user_id)
            ->where('target_user_id', $this->user_id)
            ->where('interaction_type', $this->interaction_type)
            ->exists();

        if ($mutual && !$this->is_mutual) {
            $this->update(['is_mutual' => true]);

            // Also update the reciprocal interaction
            self::where('user_id', $this->target_user_id)
                ->where('target_user_id', $this->user_id)
                ->where('interaction_type', $this->interaction_type)
                ->update(['is_mutual' => true]);
        }

        return $mutual;
    }

    /**
     * Get interaction statistics for a user
     */
    public static function getUserStats(int $userId): array
    {
        $stats = self::selectRaw('
            interaction_type,
            COUNT(*) as count,
            COUNT(CASE WHEN is_mutual THEN 1 END) as mutual_count
        ')
            ->where('user_id', $userId)
            ->groupBy('interaction_type')
            ->get()
            ->keyBy('interaction_type');

        return [
            'total_interactions' => $stats->sum('count'),
            'likes_given' => $stats->get(self::TYPE_LIKE)?->count ?? 0,
            'super_likes_given' => $stats->get(self::TYPE_SUPER_LIKE)?->count ?? 0,
            'passes_given' => $stats->get(self::TYPE_PASS)?->count ?? 0,
            'mutual_likes' => $stats->get(self::TYPE_LIKE)?->mutual_count ?? 0,
            'mutual_super_likes' => $stats->get(self::TYPE_SUPER_LIKE)?->mutual_count ?? 0,
            'like_success_rate' => $stats->get(self::TYPE_LIKE) ?
                round(($stats->get(self::TYPE_LIKE)->mutual_count / $stats->get(self::TYPE_LIKE)->count) * 100, 2) : 0
        ];
    }

    /**
     * Get daily interaction limits for user
     */
    public static function getDailyLimits(User $user): array
    {
        $today = now()->startOfDay();

        $todayStats = self::where('user_id', $user->id)
            ->where('created_at', '>=', $today)
            ->selectRaw('
                             interaction_type,
                             COUNT(*) as count
                         ')
            ->groupBy('interaction_type')
            ->get()
            ->keyBy('interaction_type');

        $limits = [
            'likes' => $user->isPremium() ? 999 : 20,        // Free users: 20/day
            'super_likes' => $user->isPremium() ? 10 : 1,    // Free users: 1/day
            'passes' => 999                                   // Unlimited
        ];

        $used = [
            'likes' => ($todayStats->get(self::TYPE_LIKE)?->count ?? 0),
            'super_likes' => ($todayStats->get(self::TYPE_SUPER_LIKE)?->count ?? 0),
            'passes' => ($todayStats->get(self::TYPE_PASS)?->count ?? 0)
        ];

        return [
            'limits' => $limits,
            'used' => $used,
            'remaining' => [
                'likes' => max(0, $limits['likes'] - $used['likes']),
                'super_likes' => max(0, $limits['super_likes'] - $used['super_likes']),
                'passes' => $limits['passes']
            ]
        ];
    }

    /**
     * Check if user can perform interaction type
     */
    public static function canPerformInteraction(User $user, string $interactionType): bool
    {
        $limits = self::getDailyLimits($user);

        switch ($interactionType) {
            case self::TYPE_LIKE:
                return $limits['remaining']['likes'] > 0;
            case self::TYPE_SUPER_LIKE:
                return $limits['remaining']['super_likes'] > 0;
            case self::TYPE_PASS:
            case self::TYPE_BLOCK:
            case self::TYPE_REPORT:
                return true;
            default:
                return false;
        }
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-check for mutual interactions when creating
        static::created(function ($interaction) {
            if ($interaction->isPositive()) {
                $interaction->checkMutualInteraction();
            }
        });
    }
}
