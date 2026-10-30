<?php

namespace App\Http\Controllers\Web;

use App\Actions\ManageTrainingSessions;
use App\Enums\TrainingSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTrainingSessionRequest;
use App\Http\Requests\CompleteTrainingSessionRequest;
use App\Http\Requests\Web\DestroyTrainingSessionRequest;
use App\Http\Requests\Web\ListTrainingSessionsRequest;
use App\Http\Requests\Web\StoreTrainingSessionRequest;
use App\Http\Requests\Web\UpdateTrainingSessionRequest;
use App\Models\TrainingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class TrainingSessionController extends Controller
{
    public function index(ListTrainingSessionsRequest $request, ManageTrainingSessions $sessions): View
    {
        $filters = $request->validated();
        $paginator = $sessions->paginate($filters);
        // Preserve the local form representation in links, not the API-style conversion.
        $paginator->appends($request->only(array_keys($filters)));

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

    public function show(TrainingSession $trainingSession): View
    {
        Gate::authorize('view', $trainingSession);

        return view('admin.training-sessions.show', compact('trainingSession'));
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
        $sessions->delete($trainingSession);

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
