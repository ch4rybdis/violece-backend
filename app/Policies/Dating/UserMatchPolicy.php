<?php

namespace App\Policies\Dating;

use App\Models\User;
use App\Models\Dating\UserMatch;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserMatchPolicy
{
    use HandlesAuthorization;

    public function view(User $user, UserMatch $match)
    {
        return $match->user1_id === $user->id || $match->user2_id === $user->id;
    }

    public function message(User $user, UserMatch $match)
    {
        return $this->view($user, $match) && $match->is_active;
    }
}
