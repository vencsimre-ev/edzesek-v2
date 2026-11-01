<?php

namespace App\Http\Controllers\Web;

use App\Actions\ManageAttendances;
use App\Actions\ManageTrainingSessions;
use App\Enums\TrainingSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTrainingSessionRequest;
use App\Http\Requests\CompleteTrainingSessionRequest;
use App\Http\Requests\ListTrainingSessionsRequest;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Http\Requests\UpdateTrainingSessionRequest;
use App\Http\Requests\Web\DestroyTrainingSessionRequest;
use App\Http\Requests\Web\ShowTrainingSessionRequest;
use App\Models\TrainingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TrainingSessionController extends Controller
{
    public function index(ListTrainingSessionsRequest $request, ManageTrainingSessions $sessions): View
    {
        $filters = $request->validated();
        $paginator = $sessions->paginate($filters);
        $paginator->load(['attendances:id,training_session_id,participant_id', 'attendances.participant:id,name,deleted_at']);

        return view('admin.training-sessions.index', [
            'sessions' => $paginator,
            'filters' => $request->only(array_keys($filters)),
        ]);
    }

    public function create(ManageTrainingSessions $sessions): View
    {
        Gate::authorize('create', TrainingSession::class);

        return view('admin.training-sessions.form', ['trainingSession' => $sessions->defaults()]);
    }

    public function store(StoreTrainingSessionRequest $request, ManageTrainingSessions $sessions): RedirectResponse
    {
        $trainingSession = $sessions->create($request->validated(), $request->user());

        return redirect()->route('admin.training-sessions.show', $trainingSession)->with('message', 'Az edzés létrehozva.');
    }

    public function show(ShowTrainingSessionRequest $request, TrainingSession $trainingSession, ManageAttendances $attendances): View
    {
        $filters = $request->validated();

        return view('admin.training-sessions.show', [
            'trainingSession' => $trainingSession,
            'attendances' => $attendances->paginate($trainingSession, 20, $request->integer('page', 1))->appends($filters),
            'participants' => $trainingSession->status === TrainingSessionStatus::Cancelled ? null
                : $attendances->candidates($trainingSession, $filters['participant_search'] ?? null,
                    $request->integer('participants_page', 1))->appends($filters),
            'participantSearch' => $filters['participant_search'] ?? '',
            'guestSubmissionId' => (string) Str::uuid(),
        ]);
    }

    public function edit(TrainingSession $trainingSession): View|RedirectResponse
    {
        Gate::authorize('update', $trainingSession);
        if ($trainingSession->status !== TrainingSessionStatus::Scheduled) {
            return redirect()->route('admin.training-sessions.show', $trainingSession)
                ->withErrors(['training_session' => 'Csak tervezett edzés adatai módosíthatók.']);
        }

        return view('admin.training-sessions.form', compact('trainingSession'));
    }

    public function update(UpdateTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): RedirectResponse
    {
        try {
            $sessions->update($trainingSession, $request->validated());
        } catch (ConflictHttpException $exception) {
            return redirect()->route('admin.training-sessions.show', $trainingSession)->withErrors(['training_session' => $exception->getMessage()]);
        }

        return redirect()->route('admin.training-sessions.show', $trainingSession)->with('message', 'Az edzés adatai mentve.');
    }

    public function destroy(DestroyTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): RedirectResponse
    {
        try {
            $sessions->delete($trainingSession);
        } catch (ConflictHttpException $exception) {
            return redirect()->route('admin.training-sessions.show', $trainingSession)->withErrors(['training_session' => $exception->getMessage()]);
        }

        return redirect()->route('admin.training-sessions.index')->with('message', 'Az edzés véglegesen törölve.');
    }

    public function complete(CompleteTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): RedirectResponse
    {
        try {
            $sessions->complete($trainingSession);
        } catch (ConflictHttpException $exception) {
            return redirect()->route('admin.training-sessions.show', $trainingSession)->withErrors(['training_session' => $exception->getMessage()]);
        }

        return redirect()->route('admin.training-sessions.show', $trainingSession)->with('message', 'Az edzés megtartottként rögzítve.');
    }

    public function cancel(CancelTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): RedirectResponse
    {
        try {
            $sessions->cancel($trainingSession, $request->validated('cancellation_reason'));
        } catch (ConflictHttpException $exception) {
            return redirect()->route('admin.training-sessions.show', $trainingSession)->withErrors(['training_session' => $exception->getMessage()]);
        }

        return redirect()->route('admin.training-sessions.show', $trainingSession)->with('message', 'Az edzés elmaradtként rögzítve.');
    }
}
