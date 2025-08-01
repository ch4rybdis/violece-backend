<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventQuestion extends Model
{
    use HasFactory;

    const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    const TYPE_SCALE = 'scale';
    const TYPE_TEXT = 'text';
    const TYPE_IMAGE_CHOICE = 'image_choice';

    protected $fillable = [
        'event_id',
        'question_type',
        'question_text',
        'options',
        'psychological_weights',
        'display_order',
        'is_required',
    ];

    protected $casts = [
        'options' => 'array',
        'psychological_weights' => 'array',
        'is_required' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(WeeklyEvent::class, 'event_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EventResponse::class, 'question_id');
    }

    public function isMultipleChoice(): bool
    {
        return $this->question_type === self::TYPE_MULTIPLE_CHOICE;
    }

    public function isScale(): bool
    {
        return $this->question_type === self::TYPE_SCALE;
    }

    public function isText(): bool
    {
        return $this->question_type === self::TYPE_TEXT;
    }

    public function isImageChoice(): bool
    {
        return $this->question_type === self::TYPE_IMAGE_CHOICE;
    }

    public function getOptionLabel(string $optionValue): ?string
    {
        if (!$this->isMultipleChoice() && !$this->isImageChoice()) {
            return null;
        }

        $options = $this->options ?? [];

        foreach ($options as $option) {
            if (($option['value'] ?? '') === $optionValue) {
                return $option['label'] ?? $optionValue;
            }
        }

        return null;
    }
}
