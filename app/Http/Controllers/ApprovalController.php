<?php

namespace App\Http\Controllers;

use App\Actions\ApproveUser;
use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(): View
    {
        return view('admin.approvals', [
            'users' => User::query()->where('approval_status', ApprovalStatus::Pending->value)
                ->whereNotNull('email_verified_at')->orderBy('id')->paginate(20),
        ]);
    }

    public function update(Request $request, User $user, ApproveUser $approve): RedirectResponse
    {
        $changed = $approve->approve($user, $request->user());

        return redirect()->route('admin.approvals.index')->with('message',
            $changed ? 'A felhasználó jóváhagyva.' : 'A felhasználó már jóvá van hagyva.');
    }
}
