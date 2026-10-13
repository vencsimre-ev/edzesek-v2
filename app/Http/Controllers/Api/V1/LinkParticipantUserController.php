<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LinkParticipantUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkParticipantUserRequest;
use App\Http\Resources\ParticipantResource;
use App\Models\Participant;

class LinkParticipantUserController extends Controller
{
    public function __invoke(LinkParticipantUserRequest $request, Participant $participant, LinkParticipantUser $link): ParticipantResource
    {
        return new ParticipantResource($link->link($participant, $request->integer('user_id')));
    }
}
