<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ManageAttendances;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListAttendancesRequest;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\TrainingSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function index(ListAttendancesRequest $request, TrainingSession $trainingSession, ManageAttendances $attendances): AnonymousResourceCollection
    {
        return AttendanceResource::collection($attendances->paginate($trainingSession,
            $request->integer('per_page', 20), $request->integer('page', 1))->appends($request->validated()));
    }

    public function store(StoreAttendanceRequest $request, TrainingSession $trainingSession, ManageAttendances $attendances): AttendanceResource
    {
        return new AttendanceResource($attendances->add($trainingSession, $request->validated(), $request->user()));
    }

    public function destroy(TrainingSession $trainingSession, Attendance $attendance, ManageAttendances $attendances): Response
    {
        Gate::authorize('update', $trainingSession);
        $attendances->remove($trainingSession, $attendance);

        return response()->noContent();
    }
}
