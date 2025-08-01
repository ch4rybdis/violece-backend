<?php

namespace App\Http\Resources\Notifications;

use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->data['type'] ?? 'notification',
            'data' => $this->data,
            'read_at' => $this->read_at ? $this->read_at->toISOString() : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
