<?php

namespace App\Http\Controllers\Web;

use App\Actions\ManageParticipants;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListParticipantsRequest;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Models\Participant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function index(ListParticipantsRequest $request, ManageParticipants $participants): View
    {
        return view('admin.participants.index', [
            'participants' => $participants->paginate($request->validated()),
            'search' => $request->validated('search', ''),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Participant::class);

        return view('admin.participants.form', ['participant' => new Participant]);
    }

    public function store(StoreParticipantRequest $request, ManageParticipants $participants): RedirectResponse
    {
        $participant = $participants->create($request->validated());

        return redirect()->route('admin.participants.show', $participant)->with('message', 'A vendég résztvevő létrehozva.');
    }

    public function show(Participant $participant): View
    {
        Gate::authorize('view', $participant);
        $participant->load('user');

        return view('admin.participants.show', compact('participant'));
    }

    public function edit(Participant $participant): View
    {
        Gate::authorize('update', $participant);

        return view('admin.participants.form', compact('participant'));
    }

    public function update(UpdateParticipantRequest $request, Participant $participant, ManageParticipants $participants): RedirectResponse
    {
        $participants->update($participant, $request->validated());

        return redirect()->route('admin.participants.show', $participant)->with('message', 'A résztvevő adatai mentve.');
    }

    public function destroy(Participant $participant, ManageParticipants $participants): RedirectResponse
    {
        Gate::authorize('delete', $participant);
        $participants->archive($participant);

        return redirect()->route('admin.participants.index')->with('message', 'A résztvevő archiválva.');
    }
}
