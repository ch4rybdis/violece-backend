<?php

namespace App\Models\Psychology;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'description',
        'is_active',
        'is_default',
        'total_questions',
        'estimated_duration_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(PsychologyQuestion::class)->orderBy('order_sequence');
    }

    public function userResponses(): HasMany
    {
        return $this->hasMany(UserResponse::class);
    }

    public static function getActive(): ?self
    {
        return self::where('is_active', true)
            ->where('is_default', true)
            ->first();
    }
}
