<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activity $activity): bool
    {
        return $activity->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Activity $activity): bool
    {
        return $activity->user_id === $user->id
            && $activity->classroom_coursework_id === null;
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $activity->user_id === $user->id
            && $activity->classroom_coursework_id === null;
    }

    public function restore(User $user, Activity $activity): bool
    {
        return $activity->user_id === $user->id;
    }
}
