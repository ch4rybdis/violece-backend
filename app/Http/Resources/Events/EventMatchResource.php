<?php

namespace App\Http\Resources\Events;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\MinimalUserResource;

class EventMatchResource extends JsonResource
{
    public function toArray($request)
    {
        $currentUserId = auth()->id();
        $otherUser = $this->user1_id === $currentUserId ? $this->user2 : $this->user1;
        $userAccepted = $this->user1_id === $currentUserId ? $this->user1_accepted : $this->user2_accepted;
        $otherUserAccepted = $this->user1_id === $currentUserId ? $this->user2_accepted : $this->user1_accepted;

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'other_user' => new MinimalUserResource($otherUser),
            'compatibility_score' => $this->compatibility_score,
            'match_reasons' => $this->match_reasons,
            'user_accepted' => $userAccepted,
            'other_user_accepted' => $otherUserAccepted,
            'both_accepted' => $this->user1_accepted && $this->user2_accepted,
            'matched_at' => $this->matched_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
