<?php

namespace App\Models\Psychology;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsychologicalQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'option_key',
        'order_sequence',
        'text',
        'visual_content',
        'trait_impacts',
    ];

    protected $casts = [
        'trait_impacts' => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(PsychologyQuestion::class, 'question_id');
    }

    public function userResponses(): HasMany
    {
        return $this->hasMany(UserResponse::class, 'selected_option_id');
    }

    public function getVisualPath(): ?string
    {
        return $this->visual_content ? "options/{$this->visual_content}" : null;
    }
}
