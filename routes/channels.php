<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Dating\UserMatch;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Match channel for private messaging
Broadcast::channel('match.{matchId}', function ($user, $matchId) {
    $match = UserMatch::find($matchId);
    if (!$match) {
        return false;
    }
    return $match->user1_id === $user->id || $match->user2_id === $user->id;
});

// User presence channel
Broadcast::channel('presence.user.{userId}', function ($user, $userId) {
    if ((string) $user->id !== (string) $userId) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->first_name,
        'photo' => $user->getPrimaryPhotoUrl(),
    ];
});

// Public notification channel for system announcements
Broadcast::channel('announcements', function ($user) {
    return true;
});
