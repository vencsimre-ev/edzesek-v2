<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ])->validate();

        $user = new User;
        $user->name = $input['name'];
        $user->email = $input['email'];
        $user->password = $input['password'];
        $user->role = Role::Admin;
        $user->approval_status = ApprovalStatus::Approved;
        $user->email_verified_at = now();
        $user->approved_at = now();
        // Bootstrap account: created via the trusted CLI, without an approving user.
        $user->approved_by = null;
        $user->save();

        return $user;
    }
}
