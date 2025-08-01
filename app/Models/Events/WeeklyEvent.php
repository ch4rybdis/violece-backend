<?php

namespace App\Models\Events;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyEvent extends Model
{
    use HasFactory;

    const TYPE_PERSONALITY_QUIZ = 'personality_quiz';
    const TYPE_SCENARIO_CHALLENGE = 'scenario_challenge';
    const TYPE_VALUES_ALIGNMENT = 'values_alignment';
    const TYPE_LIFESTYLE_MATCHING = 'lifestyle_matching';

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = 'active';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'event_type',
        'title',
        'description',
        'event_data',
        'starts_at',
        'ends_at',
        'max_participants',
        'status',
    ];

    protected $casts = [
        'event_data' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(EventQuestion::class, 'event_id');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(EventParticipation::class, 'event_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class, 'event_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function hasStarted(): bool
    {
        return now()->gte($this->starts_at);
    }

    public function hasEnded(): bool
    {
        return now()->gte($this->ends_at);
    }

    public function getParticipantCount(): int
    {
        return $this->participations()->count();
    }

    public function isFull(): bool
    {
        return $this->getParticipantCount() >= $this->max_participants;
    }
}
