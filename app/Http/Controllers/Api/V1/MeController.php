<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        Gate::authorize('view', $request->user());

        return new UserResource($request->user());
    }
}
