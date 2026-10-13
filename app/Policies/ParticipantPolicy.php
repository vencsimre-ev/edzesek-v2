<?php

namespace App\Policies;

use App\Models\Participant;
use App\Models\User;

class ParticipantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->isApproved() && $user->hasVerifiedEmail();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Participant $participant): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Participant $participant): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Participant $participant): bool
    {
        return $this->viewAny($user);
    }

    public function linkUser(User $user, Participant $participant): bool
    {
        return $this->viewAny($user);
    }
}
