<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ManageTrainingSessions;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTrainingSessionRequest;
use App\Http\Requests\CompleteTrainingSessionRequest;
use App\Http\Requests\ListTrainingSessionsRequest;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Http\Requests\UpdateTrainingSessionRequest;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TrainingSessionController extends Controller
{
    public function syncWeekly(Request $request, ManageTrainingSessions $sessions): JsonResponse
    {
        $key = config('training.cron_key');
        abort_unless(is_string($key) && $key !== '', 503, 'A cron kulcsa nincs beállítva.');
        abort_unless(hash_equals($key, $request->bearerToken() ?? ''), 401, 'Érvénytelen cron kulcs.');

        $admin = User::query()->where('role', Role::Admin)->where('approval_status', ApprovalStatus::Approved)
            ->whereNotNull('email_verified_at')->orderBy('id')->first();
        abort_unless($admin, 422, 'Az automatikus létrehozáshoz megerősített, jóváhagyott admin szükséges.');

        $counts = DB::transaction(function () use ($sessions, $admin) {
            $defaults = $sessions->defaults();
            $first = $defaults->starts_at->setTimezone(config('training.timezone'));
            $created = 0;
            for ($week = 0; $week < 8; $week++) {
                $start = $first->addWeeks($week);
                $day = $start->startOfDay();
                if (TrainingSession::query()->where('starts_at', '>=', $day->utc()->format('Y-m-d H:i:s.u'))
                    ->where('starts_at', '<', $day->addDay()->utc()->format('Y-m-d H:i:s.u'))->exists()) {
                    continue;
                }
                $sessions->create([
                    'title' => $defaults->title, 'location' => $defaults->location,
                    'starts_at' => $start->format('Y-m-d\TH:i'),
                    'ends_at' => $start->addHours(2)->format('Y-m-d\TH:i'),
                ], $admin);
                $created++;
            }

            return ['created' => $created, 'completed' => $sessions->completeDue()];
        }, 5);

        return response()->json(['data' => $counts]);
    }

    public function index(ListTrainingSessionsRequest $request, ManageTrainingSessions $sessions): AnonymousResourceCollection
    {
        return TrainingSessionResource::collection($sessions->paginate($request->validated()));
    }

    public function store(StoreTrainingSessionRequest $request, ManageTrainingSessions $sessions): TrainingSessionResource
    {
        return new TrainingSessionResource($sessions->create($request->validated(), $request->user()));
    }

    public function show(TrainingSession $trainingSession): TrainingSessionResource
    {
        Gate::authorize('view', $trainingSession);

        return new TrainingSessionResource($trainingSession);
    }

    public function update(UpdateTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): TrainingSessionResource
    {
        return new TrainingSessionResource($sessions->update($trainingSession, $request->validated()));
    }

    public function destroy(TrainingSession $trainingSession, ManageTrainingSessions $sessions): Response
    {
        Gate::authorize('delete', $trainingSession);
        $sessions->delete($trainingSession);

        return response()->noContent();
    }

    public function complete(CompleteTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): TrainingSessionResource
    {
        return new TrainingSessionResource($sessions->complete($trainingSession));
    }

    public function cancel(CancelTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): TrainingSessionResource
    {
        return new TrainingSessionResource($sessions->cancel($trainingSession, $request->validated('cancellation_reason')));
    }
}
