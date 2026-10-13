<?php

namespace App\Http\Controllers\Web;

use App\Actions\LinkParticipantUser;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkParticipantUserRequest;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LinkParticipantUserController extends Controller
{
    public function create(Request $request, Participant $participant): View
    {
        Gate::authorize('linkUser', $participant);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $search = $filters['search'] ?? '';
        $users = User::query()->where('role', Role::Student)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('instr(lower(name), lower(?)) > 0', [$search])
                ->orWhereRaw('instr(lower(email), lower(?)) > 0', [$search])))
            ->orderBy('id')->paginate(20)->appends($filters);

        return view('admin.participants.link', compact('participant', 'users', 'search'));
    }

    public function store(LinkParticipantUserRequest $request, Participant $participant, LinkParticipantUser $link): RedirectResponse
    {
        try {
            $link->link($participant, $request->integer('user_id'));
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['user_id' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('admin.participants.show', $participant)->with('message', 'A résztvevő és a student fiók összekapcsolva.');
    }
}
