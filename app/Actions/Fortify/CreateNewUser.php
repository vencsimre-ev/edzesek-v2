<?php

namespace App\Actions\Fortify;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ])->validate();

        $user = new User;
        $user->name = $input['name'];
        $user->email = $input['email'];
        $user->password = $input['password'];
        $user->role = Role::Student;
        $user->approval_status = ApprovalStatus::Pending;
        $user->save();

        return $user;
    }
}
