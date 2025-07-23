<?php

namespace App\Models\Dating;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class UserInteraction extends Model
{
    use HasFactory;

    const TYPE_PASS = 1;
    const TYPE_LIKE = 2;
    const TYPE_SUPER_LIKE = 3;

    protected $fillable = [
        'user_id',
        'target_user_id',
        'interaction_type',
        'interaction_context',
    ];

    protected $casts = [
        'interaction_context' => 'array',
        'interaction_type' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function isLike(): bool
    {
        return $this->interaction_type === self::TYPE_LIKE;
    }

    public function isSuperLike(): bool
    {
        return $this->interaction_type === self::TYPE_SUPER_LIKE;
    }

    public function isPass(): bool
    {
        return $this->interaction_type === self::TYPE_PASS;
    }
}
