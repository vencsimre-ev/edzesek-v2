<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListUsers;
use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $users): AnonymousResourceCollection
    {
        $paginator = $users->paginate(
            $request->enum('approval_status', ApprovalStatus::class),
            $request->integer('per_page', 20), $request->integer('page', 1),
        );
        $paginator->appends($request->validated());

        return UserResource::collection($paginator);
    }
}
