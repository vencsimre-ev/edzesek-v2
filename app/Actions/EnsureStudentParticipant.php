<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Participant;
use App\Models\User;

class EnsureStudentParticipant
{
    public function ensure(User $user): ?Participant
    {
        if ($user->role !== Role::Student) {
            return null;
        }

        // Archived participants still reserve their account connection.
        return $user->participant()->withTrashed()->firstOrCreate(
            [],
            ['name' => $user->name, 'email' => $user->email],
        );
    }
}
