<?php

namespace App\Listeners;

use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserAwaitingApproval;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

class NotifyAdminsOfVerifiedUser
{
    public function handle(Verified $event): void
    {
        $id = $event->user->getKey();
        DB::afterCommit(function () use ($id) {
            $student = User::find($id);
            if (! $student || ! $student->hasVerifiedEmail() || $student->isApproved()) {
                return;
            }

            User::query()->where('role', Role::Admin->value)
                ->where('approval_status', ApprovalStatus::Approved->value)
                ->whereNotNull('email_verified_at')
                ->each(fn (User $admin) => $admin->notify(new UserAwaitingApproval($student)));
        });
    }
}
