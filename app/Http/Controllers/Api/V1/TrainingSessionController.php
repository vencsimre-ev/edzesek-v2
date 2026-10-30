<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ManageTrainingSessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelTrainingSessionRequest;
use App\Http\Requests\CompleteTrainingSessionRequest;
use App\Http\Requests\ListTrainingSessionsRequest;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Http\Requests\UpdateTrainingSessionRequest;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrainingSessionController extends Controller
{
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

    public function complete(CompleteTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): TrainingSessionResource
    {
        return new TrainingSessionResource($sessions->complete($trainingSession));
    }

    public function cancel(CancelTrainingSessionRequest $request, TrainingSession $trainingSession, ManageTrainingSessions $sessions): TrainingSessionResource
    {
        return new TrainingSessionResource($sessions->cancel($trainingSession, $request->validated('cancellation_reason')));
    }
}
