<?php

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventParticipation extends Model
{
    use HasFactory;

    const STATUS_JOINED = 'joined';
    const STATUS_COMPLETED = 'completed';
    const STATUS_MATCHED = 'matched';
    const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'completed_at',
        'response_data',
    ];

    protected $casts = [
        'response_data' => 'array',
        'completed_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WeeklyEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EventResponse::class, 'participation_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function markAsMatched(): void
    {
        $this->update([
            'status' => self::STATUS_MATCHED,
        ]);
    }
}
