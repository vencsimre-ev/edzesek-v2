<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\User;

class TrainingSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isApproved() && $user->hasVerifiedEmail();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, TrainingSession $trainingSession): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, TrainingSession $trainingSession): bool
    {
        return $this->viewAny($user);
    }

    public function complete(User $user, TrainingSession $trainingSession): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, TrainingSession $trainingSession): bool
    {
        return $this->viewAny($user);
    }
}
