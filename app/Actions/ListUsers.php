<?php

namespace App\Actions;

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListUsers
{
    public function paginate(?ApprovalStatus $status = null, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return User::query()
            ->when($status, fn ($query) => $query->where('approval_status', $status->value))
            ->when($status === ApprovalStatus::Pending, fn ($query) => $query->whereNotNull('email_verified_at'))
            ->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
    }
}
