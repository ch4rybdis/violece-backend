<?php

namespace App\Models\Dating;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\User;

class Message extends Model
{
    use HasFactory, HasUuids;

    const TYPE_TEXT = 1;
    const TYPE_IMAGE = 2;
    const TYPE_GIF = 3;
    const TYPE_VOICE = 4;

    protected $fillable = [
        'match_id',
        'sender_id',
        'message_text',
        'message_type',
        'message_metadata',
        'delivered_at',
        'read_at',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'message_metadata' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_deleted' => 'boolean',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(UserMatch::class, 'match_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function isText(): bool
    {
        return $this->message_type === self::TYPE_TEXT;
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function isDelivered(): bool
    {
        return !is_null($this->delivered_at);
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }
}
