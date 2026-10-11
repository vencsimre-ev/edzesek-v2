<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function view(User $actor, User $user): bool
    {
        return $actor->is($user) || $this->viewAny($actor);
    }

    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin() && $actor->isApproved() && $actor->hasVerifiedEmail();
    }

    public function approve(User $actor, User $user): bool
    {
        return $this->viewAny($actor);
    }
}
