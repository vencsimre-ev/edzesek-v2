<?php

namespace App\Http\Controllers\Web;

use App\Actions\ApproveUser;
use App\Actions\ListUsers;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveUserRequest;
use App\Http\Requests\ListUsersRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $users): View
    {
        return view('admin.approvals', [
            'users' => $users->paginate(ApprovalStatus::Pending, 20, $request->integer('page', 1)),
        ]);
    }

    public function update(ApproveUserRequest $request, User $user, ApproveUser $approve): RedirectResponse
    {
        $changed = $approve->approve($user, $request->user());

        return redirect()->route('admin.approvals.index')->with('message',
            $changed ? 'A felhasználó jóváhagyva.' : 'A felhasználó már jóvá van hagyva.');
    }
}
