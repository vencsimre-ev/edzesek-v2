<?php

namespace App\Http\Controllers\Web;

use App\Actions\ManageAttendances;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\Web\DestroyAttendanceRequest;
use App\Models\Attendance;
use App\Models\TrainingSession;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AttendanceController extends Controller
{
    public function store(StoreAttendanceRequest $request, TrainingSession $trainingSession, ManageAttendances $attendances): RedirectResponse
    {
        $redirect = redirect()->route('admin.training-sessions.show', [
            'trainingSession' => $trainingSession,
            'participant_search' => $request->validated('participant_search'),
        ])->with('participant_picker_open', $request->filled('participant_id'));

        try {
            $attendances->add($trainingSession, $request->validated(), $request->user());
        } catch (ConflictHttpException $exception) {
            return $redirect->withErrors(['attendance' => $exception->getMessage()]);
        }

        return $redirect->with('message', 'A résztvevő hozzáadva a névsorhoz.');
    }

    public function destroy(DestroyAttendanceRequest $request, TrainingSession $trainingSession, Attendance $attendance, ManageAttendances $attendances): RedirectResponse
    {
        $attendances->remove($trainingSession, $attendance);

        return redirect()->route('admin.training-sessions.show', [
            'trainingSession' => $trainingSession,
            'participant_search' => $request->validated('participant_search'),
        ])->with('participant_picker_open', $request->boolean('participant_picker_open'))
            ->with('message', 'A részvétel eltávolítva. A személy adatai megmaradtak.');
    }
}
