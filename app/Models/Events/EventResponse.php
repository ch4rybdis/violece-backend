<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'participation_id',
        'question_id',
        'response_value',
        'response_metadata',
        'response_time_ms',
    ];

    protected $casts = [
        'response_metadata' => 'array',
        'response_time_ms' => 'integer',
    ];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(EventParticipation::class, 'participation_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(EventQuestion::class, 'question_id');
    }

    public function getLabel(): ?string
    {
        return $this->question->getOptionLabel($this->response_value);
    }
}
