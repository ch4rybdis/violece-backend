<?php

namespace App\Events\Dating;

use App\Models\Dating\DateSuggestion;
use App\Models\Dating\UserMatch;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DateSuggested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $dateSuggestion;
    public $match;

    public function __construct(DateSuggestion $dateSuggestion)
    {
        $this->dateSuggestion = $dateSuggestion;
        $this->match = $dateSuggestion->match;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('match.' . $this->match->id);
    }

    public function broadcastAs()
    {
        return 'date-suggested';
    }

    public function broadcastWith()
    {
        return [
            'suggestion' => [
                'id' => $this->dateSuggestion->id,
                'activity' => $this->dateSuggestion->activity_name,
                'description' => $this->dateSuggestion->activity_description,
                'venue' => $this->dateSuggestion->venue_name,
                'day' => $this->dateSuggestion->day_name,
                'time' => $this->dateSuggestion->formatted_time,
                'compatibility_reason' => $this->dateSuggestion->compatibility_reason,
            ],
            'match_id' => $this->match->id,
            'timestamp' => now()->toISOString()
        ];
    }
}
