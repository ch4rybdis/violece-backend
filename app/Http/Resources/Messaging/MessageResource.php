<?php

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'sender_id' => $this->sender_id,
            'content' => $this->content,
            'type' => $this->type,
            'meta' => $this->meta,
            'is_sent_by_me' => $this->sender_id === auth()->id(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->first_name,
                'photo' => $this->sender->getPrimaryPhotoUrl(),
            ],
            'status' => $this->getMessageStatus(),
        ];
    }

    private function getMessageStatus(): string
    {
        $match = $this->match;

        // If the current user sent this message
        if ($this->sender_id === auth()->id()) {
            // Check if it's been read by the recipient
            if ($match->user1_id === auth()->id()) {
                // Current user is user1, check if user2 has read it
                if ($match->user2_last_read_at && $match->user2_last_read_at >= $this->created_at) {
                    return 'read';
                }
            } else {
                // Current user is user2, check if user1 has read it
                if ($match->user1_last_read_at && $match->user1_last_read_at >= $this->created_at) {
                    return 'read';
                }
            }

            return 'delivered';
        }

        return 'received';
    }
}
