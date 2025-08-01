<?php

namespace App\Models\Events;

use App\Models\User;
use App\Models\Dating\UserMatch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user1_id',
        'user2_id',
        'compatibility_score',
        'match_reasons',
        'is_notified',
        'notified_at',
        'user1_accepted',
        'user2_accepted',
        'matched_at',
    ];

    protected $casts = [
        'compatibility_score' => 'float',
        'match_reasons' => 'array',
        'is_notified' => 'boolean',
        'notified_at' => 'datetime',
        'user1_accepted' => 'boolean',
        'user2_accepted' => 'boolean',
        'matched_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WeeklyEvent::class, 'event_id');
    }

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function isAccepted(): bool
    {
        return $this->user1_accepted && $this->user2_accepted;
    }

    public function convertToUserMatch(): ?UserMatch
    {
        if (!$this->isAccepted()) {
            return null;
        }

        // Create a regular user match from this event match
        return UserMatch::create([
            'user1_id' => min($this->user1_id, $this->user2_id),
            'user2_id' => max($this->user1_id, $this->user2_id),
            'compatibility_score' => $this->compatibility_score,
            'match_source' => 'event',
            'match_source_id' => $this->event_id,
            'matched_at' => now(),
            'last_activity_at' => now(),
        ]);
    }

    public function acceptMatch(int $userId): void
    {
        if ($userId === $this->user1_id) {
            $this->update(['user1_accepted' => true]);
        } elseif ($userId === $this->user2_id) {
            $this->update(['user2_accepted' => true]);
        }

        // If both users accepted, set matched_at
        if ($this->user1_accepted && $this->user2_accepted) {
            $this->update(['matched_at' => now()]);
            $this->convertToUserMatch();
        }
    }
}
