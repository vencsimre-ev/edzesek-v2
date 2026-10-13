<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

class AuthenticateWithGoogle
{
    public function authenticate(GoogleUser $google, ?User $linkTo = null): User
    {
        $data = Validator::make([
            'google_id' => $google->getId(),
            'email' => $google->getEmail(),
            'name' => $google->getName() ?: $google->getEmail(),
        ], [
            'google_id' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ])->validate();
        if (($google->getRaw()['email_verified'] ?? false) !== true) {
            throw ValidationException::withMessages(['google' => 'A Google-fiók emailcíme nincs megerősítve.']);
        }
        $data['email'] = Str::lower($data['email']);

        try {
            return DB::transaction(function () use ($data, $linkTo) {
                $linked = User::query()->where('google_id', $data['google_id'])->first();
                if ($linkTo) {
                    $user = $linkTo->fresh();
                    if (! $user || ! $user->hasVerifiedEmail() || $user->email !== $data['email']
                        || ($linked && ! $linked->is($user))
                        || ($user->google_id && $user->google_id !== $data['google_id'])) {
                        throw ValidationException::withMessages(['google' => 'Ez a Google-fiók nem kapcsolható a belépett fiókhoz. Az emailcímeknek egyezniük kell.']);
                    }
                    if (! $user->google_id) {
                        $user->google_id = $data['google_id'];
                        $user->save();
                    }

                    return $user;
                }
                if ($linked) {
                    return $linked;
                }
                if (User::query()->where('email', $data['email'])->exists()) {
                    throw ValidationException::withMessages(['google' => 'Ezzel az emailcímmel már van fiók. Lépj be a meglévő fiókoddal, majd kapcsold hozzá a Google-fiókot.']);
                }

                $user = new User;
                $user->name = $data['name'];
                $user->email = $data['email'];
                // Unknown random password; the normal reset flow can set a local password later.
                $user->password = Str::random(64);
                $user->google_id = $data['google_id'];
                $user->role = Role::Student;
                $user->approval_status = ApprovalStatus::Pending;
                $user->email_verified_at = now();
                $user->save();
                app(EnsureStudentParticipant::class)->ensure($user);
                DB::afterCommit(fn () => event(new Verified($user)));

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['google' => 'A fiók már létezik vagy már össze van kapcsolva. Lépj be, és próbáld újra.']);
        }
    }
}
