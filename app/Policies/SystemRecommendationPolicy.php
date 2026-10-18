<?php

namespace App\Policies;

use App\Models\SystemRecommendation;
use App\Models\User;

class SystemRecommendationPolicy
{
    /** Solo el dueño puede marcar una recomendación como leída. */
    public function update(User $user, SystemRecommendation $recommendation): bool
    {
        return (int) $recommendation->user_id === (int) $user->id;
    }
}
