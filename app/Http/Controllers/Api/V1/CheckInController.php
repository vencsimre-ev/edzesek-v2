<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ManageAttendances;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use App\Http\Resources\CheckInResource;
use Illuminate\Http\JsonResponse;

class CheckInController extends Controller
{
    public function index(CheckInRequest $request, ManageAttendances $attendances): CheckInResource
    {
        return new CheckInResource($attendances->checkInPage(
            $request->filled('training_session_id') ? $request->integer('training_session_id') : null,
            $request->user(), $request->session(),
        ));
    }

    public function store(CheckInRequest $request, ManageAttendances $attendances): JsonResponse
    {
        $result = $attendances->checkIn($request->validated(), $request->user(), $request->session());

        return (new CheckInResource($result))->response()->setStatusCode($result['already_recorded'] ? 200 : 201);
    }

    public function withdraw(CheckInRequest $request, ManageAttendances $attendances): CheckInResource
    {
        return new CheckInResource($attendances->withdraw($request->validated(), $request->user(), $request->session()));
    }
}
