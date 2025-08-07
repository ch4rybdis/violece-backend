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

    // Update these to use strings to align with controller/requests
    const TYPE_TEXT = 'text';
    const TYPE_IMAGE = 'image';
    const TYPE_GIF = 'gif';
    const TYPE_AUDIO = 'audio';
    const TYPE_LOCATION = 'location';

    protected $fillable = [
        'match_id',
        'sender_id',
        'content',         // Changed from message_text
        'type',            // Changed from message_type
        'meta',            // Changed from message_metadata
        'delivered_at',
        'read_at',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'meta' => 'array',  // Changed from message_metadata
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
        return $this->type === self::TYPE_TEXT;  // Changed from message_type
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

    /**
     * Get the receiver of this message
     */
    public function getReceiver(): ?User
    {
        $match = $this->match;
        $senderId = $this->sender_id;

        if ($match->user1_id === $senderId) {
            return $match->user2;
        } else if ($match->user2_id === $senderId) {
            return $match->user1;
        }

        return null;
    }
}
