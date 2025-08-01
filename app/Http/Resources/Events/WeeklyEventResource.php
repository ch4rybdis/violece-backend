<?php

namespace App\Http\Resources\Events;

use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyEventResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->event_type,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at->toISOString(),
            'ends_at' => $this->ends_at->toISOString(),
            'status' => $this->status,
            'participant_count' => $this->getParticipantCount(),
            'max_participants' => $this->max_participants,
            'is_active' => $this->isActive(),
            'has_started' => $this->hasStarted(),
            'has_ended' => $this->hasEnded(),
            'has_joined' => $this->has_joined ?? false,
            'participation_status' => $this->participation_status ?? null,
            'completed_at' => $this->completed_at ? $this->completed_at->toISOString() : null,
            'theme' => $this->event_data['theme'] ?? null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
