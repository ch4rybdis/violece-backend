<?php

namespace App\Models\Psychology;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsychologyQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_set_id',
        'order_sequence',
        'content_key',
        'category',
        'title',
        'scenario_text',
        'video_filename',
        'image_filename',
        'psychological_weights',
        'is_required',
    ];

    protected $casts = [
        'psychological_weights' => 'array',
        'is_required' => 'boolean',
    ];

    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(PsychologyQuestionOption::class, 'question_id')->orderBy('order_sequence');
    }

    public function userResponses(): HasMany
    {
        return $this->hasMany(UserResponse::class, 'question_id');
    }

    // Violece-specific methods
    public function getVideoPath(): ?string
    {
        return $this->video_filename ? "scenarios/{$this->video_filename}" : null;
    }

    public function getImagePath(): ?string
    {
        return $this->image_filename ? "scenarios/{$this->image_filename}" : null;
    }
}
