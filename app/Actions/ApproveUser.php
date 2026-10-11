<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Notifications\AccountApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApproveUser
{
    public function approve(User $student, User $admin): bool
    {
        return DB::transaction(function () use ($student, $admin) {
            $admin = $admin->fresh();
            abort_unless($admin, 403);
            Gate::forUser($admin)->authorize('approve', $student);

            $student = $student->fresh();
            if (! $student->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['user' => 'Csak megerősített emailcímű felhasználó hagyható jóvá.']);
            }

            $updated = User::query()->whereKey($student->id)
                ->where('approval_status', ApprovalStatus::Pending->value)
                ->whereNotNull('email_verified_at')
                ->update([
                    'approval_status' => ApprovalStatus::Approved->value,
                    'approved_at' => now(),
                    'approved_by' => $admin->id,
                ]);

            if ($updated) {
                DB::afterCommit(fn () => $student->fresh()->notify(new AccountApproved));
            }

            return (bool) $updated;
        });
    }
}
