<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'first_name' => $this->first_name,
            'age' => $this->age(),
            'gender' => $this->gender,
            'preference_gender' => $this->preference_gender,
            'profile_photos' => $this->profile_photos,
            'bio' => $this->bio,
            'interests' => $this->interests,
            'profile_completion_score' => $this->profile_completion_score,
            'is_premium' => $this->isPremium(),
            'is_verified' => $this->is_verified,
            'is_online' => $this->isOnline(),
            'last_active_at' => $this->last_active_at?->diffForHumans(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
