<?php

namespace App\Http\Controllers\Web;

use App\Actions\ManageAttendances;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckInController extends Controller
{
    public function welcome(Request $request, ManageAttendances $attendances): View
    {
        return view('welcome', $attendances->publicSessions($request->user()));
    }

    public function index(CheckInRequest $request, ManageAttendances $attendances): View
    {
        $data = $attendances->checkInPage($request->filled('training_session_id') ? $request->integer('training_session_id') : null,
            $request->user(), $request->session());

        return view('check-in', $data + ['submissionId' => (string) Str::uuid()]);
    }

    public function store(CheckInRequest $request, ManageAttendances $attendances): RedirectResponse
    {
        $redirect = redirect()->route('check-in.index', ['training_session_id' => $request->integer('training_session_id')]);
        try {
            $result = $attendances->checkIn($request->validated(), $request->user(), $request->session());
        } catch (HttpException $exception) {
            if (! in_array($exception->getStatusCode(), [409, 429])) {
                throw $exception;
            }

            return $redirect->withErrors(['check_in' => $exception->getMessage()]);
        }

        return $redirect->with('message', $result['message'].' '.$result['selected']->starts_at
            ->setTimezone(config('training.timezone'))->format('Y. m. d.'));
    }

    public function withdraw(CheckInRequest $request, ManageAttendances $attendances): RedirectResponse
    {
        $result = $attendances->withdraw($request->validated(), $request->user(), $request->session());

        return redirect()->route('check-in.index', ['training_session_id' => $result['selected']->id])
            ->with('message', $result['message']);
    }
}
