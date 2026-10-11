<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ApproveUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;

class ApproveUserController extends Controller
{
    public function __invoke(ApproveUserRequest $request, User $user, ApproveUser $approve): UserResource
    {
        $approve->approve($user, $request->user());

        return new UserResource($user->fresh());
    }
}
