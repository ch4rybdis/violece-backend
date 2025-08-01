<?php

namespace App\Events;

use App\Models\User;
use App\Models\Dating\UserMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;
    public $match;
    public $isTyping;

    public function __construct(User $user, UserMatch $match, bool $isTyping)
    {
        $this->user = $user;
        $this->match = $match;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('match.' . $this->match->id);
    }

    public function broadcastAs()
    {
        return 'typing';
    }

    public function broadcastWith()
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->first_name,
            ],
            'match_id' => $this->match->id,
            'is_typing' => $this->isTyping,
            'timestamp' => now()->toISOString()
        ];
    }
}
