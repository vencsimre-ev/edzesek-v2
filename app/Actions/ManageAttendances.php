<?php

namespace App\Actions;

use App\Enums\TrainingSessionStatus;
use App\Models\Attendance;
use App\Models\Participant;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ManageAttendances
{
    public function paginate(TrainingSession $session, int $perPage = 20, int $page = 1): LengthAwarePaginator
    {
        return $session->attendances()->with('participant:id,name,deleted_at')
            ->orderBy('id')->paginate($perPage, ['*'], 'page', $page);
    }

    public function candidates(TrainingSession $session, ?string $search = null, int $page = 1): LengthAwarePaginator
    {
        return Participant::query()->select(['id', 'name'])
            ->when(filled($search), fn ($query) => $query->whereRaw('instr(lower(name), lower(?)) > 0', [$search]))
            ->whereDoesntHave('attendances', fn ($query) => $query->where('training_session_id', $session->id))
            ->orderBy('name')->orderBy('id')->paginate(20, ['*'], 'participants_page', $page);
    }

    public function add(TrainingSession $session, array $data, User $admin): Attendance
    {
        try {
            return DB::transaction(function () use ($session, $data, $admin) {
                $current = TrainingSession::query()->findOrFail($session->id);
                if ($current->status === TrainingSessionStatus::Cancelled) {
                    throw new ConflictHttpException('Elmaradt edzéshez nem adható részvétel.');
                }
                $submissionId = $data['submission_id'] ?? null;
                if ($submissionId && Attendance::query()->where('submission_id', $submissionId)->exists()) {
                    throw new ConflictHttpException('Ezt a vendéget ezzel az űrlappal már hozzáadtad.');
                }
                if (isset($data['participant_id'])) {
                    $participant = Participant::query()->find($data['participant_id']);
                    if (! $participant) {
                        throw ValidationException::withMessages(['participant_id' => 'Csak meglévő, aktív résztvevő választható.']);
                    }
                } else {
                    $participant = app(ManageParticipants::class)->create(['name' => $data['name']]);
                }
                $attendance = new Attendance;
                $attendance->training_session_id = $current->id;
                $attendance->participant_id = $participant->id;
                $attendance->created_by = $admin->id;
                $attendance->submission_id = $submissionId;
                $attendance->save();

                return $attendance->load('participant:id,name,deleted_at');
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('A résztvevő már szerepel az edzés névsorában, vagy ezt a vendégűrlapot már elküldted.');
        }
    }

    public function remove(TrainingSession $session, Attendance $attendance): void
    {
        abort_unless($attendance->training_session_id === $session->id, 404);
        $attendance->delete();
    }
}
