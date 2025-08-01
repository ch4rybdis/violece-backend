<?php

namespace App\Http\Resources\Dating;

use App\Models\Dating\DateSuggestion;
use Illuminate\Http\Resources\Json\JsonResource;

class DateSuggestionResource extends JsonResource
{
    public function toArray($request)
    {
        /** @var DateSuggestion $this */
        return [
            'id' => $this->id,
            'activity' => [
                'name' => $this->activity_name,
                'type' => $this->activity_type,
                'description' => $this->activity_description,
            ],
            'venue' => [
                'name' => $this->venue_name,
                'address' => $this->venue_address,
                'location' => [
                    'latitude' => $this->venue_latitude,
                    'longitude' => $this->venue_longitude,
                ],
            ],
            'timing' => [
                'day' => $this->day_name,
                'day_of_week' => $this->suggested_day,
                'time' => $this->formatted_time,
                'raw_time' => $this->suggested_time,
            ],
            'compatibility_reason' => $this->compatibility_reason,
            'status' => $this->is_accepted ? 'accepted' : ($this->is_rejected ? 'rejected' : 'pending'),
            'response_at' => $this->response_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
