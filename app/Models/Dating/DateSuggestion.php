<?php

namespace App\Models\Dating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DateSuggestion extends Model
{
    protected $fillable = [
        'match_id',
        'activity_type',
        'activity_name',
        'activity_description',
        'venue_name',
        'venue_address',
        'venue_latitude',
        'venue_longitude',
        'suggested_day',
        'suggested_time',
        'compatibility_reason',
        'is_accepted',
        'is_rejected',
        'response_at',
    ];

    protected $casts = [
        'venue_latitude' => 'float',
        'venue_longitude' => 'float',
        'suggested_day' => 'integer',
        'is_accepted' => 'boolean',
        'is_rejected' => 'boolean',
        'response_at' => 'datetime',
    ];

    /**
     * Get the match this suggestion belongs to
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(UserMatch::class, 'match_id');
    }

    /**
     * Get day name from suggested day
     */
    public function getDayNameAttribute(): string
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$this->suggested_day] ?? 'Unknown';
    }

    /**
     * Get formatted suggested time
     */
    public function getFormattedTimeAttribute(): string
    {
        return date('g:i A', strtotime($this->suggested_time));
    }

    /**
     * Check if suggestion is pending response
     */
    public function isPending(): bool
    {
        return !$this->is_accepted && !$this->is_rejected;
    }

    /**
     * Accept suggestion
     */
    public function accept(): void
    {
        $this->update([
            'is_accepted' => true,
            'is_rejected' => false,
            'response_at' => now(),
        ]);
    }

    /**
     * Reject suggestion
     */
    public function reject(): void
    {
        $this->update([
            'is_accepted' => false,
            'is_rejected' => true,
            'response_at' => now(),
        ]);
    }
}
