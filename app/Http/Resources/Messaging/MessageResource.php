<?php
// MessageResource.php
namespace App\Http\Resources\Messaging;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->uuid,
            'match_id' => $this->match_id,
            'sender_id' => $this->sender_id,
            'content' => $this->content,
            'type' => $this->type,
            'meta' => $this->meta,
            'delivered_at' => $this->delivered_at?->toISOString(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->first_name,
                'photo' => $this->sender->getPrimaryPhotoUrl()
            ],
            'is_own_message' => $this->sender_id === auth()->id()
        ];
    }
}
