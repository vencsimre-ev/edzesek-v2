<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ManageParticipants;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListParticipantsRequest;
use App\Http\Requests\StoreParticipantRequest;
use App\Http\Requests\UpdateParticipantRequest;
use App\Http\Resources\ParticipantResource;
use App\Models\Participant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ParticipantController extends Controller
{
    public function index(ListParticipantsRequest $request, ManageParticipants $participants): AnonymousResourceCollection
    {
        return ParticipantResource::collection($participants->paginate($request->validated()));
    }

    public function store(StoreParticipantRequest $request, ManageParticipants $participants): ParticipantResource
    {
        return new ParticipantResource($participants->create($request->validated()));
    }

    public function show(Participant $participant): ParticipantResource
    {
        Gate::authorize('view', $participant);

        return new ParticipantResource($participant);
    }

    public function update(UpdateParticipantRequest $request, Participant $participant, ManageParticipants $participants): ParticipantResource
    {
        return new ParticipantResource($participants->update($participant, $request->validated()));
    }

    public function destroy(Participant $participant, ManageParticipants $participants): Response
    {
        Gate::authorize('delete', $participant);
        $participants->archive($participant);

        return response()->noContent();
    }
}
