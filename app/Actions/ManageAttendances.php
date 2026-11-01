<?php

namespace App\Actions;

use App\Enums\TrainingSessionStatus;
use App\Models\Attendance;
use App\Models\Participant;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public function add(TrainingSession $session, array $data, ?User $creator): Attendance
    {
        try {
            return DB::transaction(function () use ($session, $data, $creator) {
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
                $attendance->created_by = $creator?->id;
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

    public function checkInSessions(): Builder
    {
        return TrainingSession::query()->where('status', '!=', TrainingSessionStatus::Cancelled)
            ->where('starts_at', '>=', now()->subDays(7)->utc()->format('Y-m-d H:i:s.u'))
            ->where('starts_at', '<=', now()->addWeeks(8)->utc()->format('Y-m-d H:i:s.u'));
    }

    public function checkInPage(?int $selectedId, ?User $user, Session $browser): array
    {
        $sessions = $this->checkInSessions()->orderBy('starts_at')->orderBy('id')->get();
        $participant = $user?->participant()->first();
        $selected = $selectedId === null ? $this->nearestSession($sessions) : $sessions->firstWhere('id', $selectedId);
        if ($selectedId !== null && ! $selected) {
            throw ValidationException::withMessages(['training_session_id' => 'Ez az edzés most nem választható.']);
        }

        return [
            'sessions' => $sessions,
            'selected' => $selected,
            'name' => $user?->name,
            'already_recorded' => $selected && $participant
                ? $selected->attendances()->where('participant_id', $participant->id)->exists() : false,
            'retry_after' => RateLimiter::availableIn($this->checkInKey($browser)),
        ];
    }

    public function checkIn(array $data, ?User $user, Session $browser): array
    {
        $key = $this->checkInKey($browser);

        $result = Cache::lock($key.':lock', 10)->get(function () use ($data, $user, $key) {
            abort_if($user && (! $user->hasVerifiedEmail() || ! $user->isApproved()), 403, 'Megerősített, jóváhagyott fiók szükséges.');
            $participant = $user?->participant()->withTrashed()->first();
            abort_if($participant?->trashed() || ($user && ! $participant && ! $user->isAdmin()),
                403, 'A fiókhoz nem tartozik aktív résztvevő. Kérj segítséget az edzőtől.');

            $result = DB::transaction(function () use ($data, $user, $participant, $key) {
                $selected = $this->checkInSessions()->whereKey($data['training_session_id'])->first();
                if (! $selected) {
                    throw ValidationException::withMessages(['training_session_id' => 'Ez az edzés most nem választható.']);
                }
                $alreadyRecorded = $participant && $selected->attendances()->where('participant_id', $participant->id)->exists();
                if (! $alreadyRecorded) {
                    $remaining = RateLimiter::availableIn($key);
                    abort_if($remaining > 0, 429, 'Új beküldés előtt várj még '.$remaining.' másodpercet.', ['Retry-After' => (string) $remaining]);
                    if ($user && ! $participant) {
                        $participant = $user->participant()->firstOrCreate([], ['name' => $user->name, 'email' => $user->email]);
                    }
                    $this->add($selected, $participant ? ['participant_id' => $participant->id] : [
                        'name' => $data['name'], 'submission_id' => $data['submission_id'],
                    ], $user);
                }

                return ['selected' => $selected, 'already_recorded' => (bool) $alreadyRecorded];
            }, 5);
            if (! $result['already_recorded']) {
                RateLimiter::hit($key, 60);
            }

            return $result + [
                'retry_after' => RateLimiter::availableIn($key),
                'message' => $result['already_recorded']
                    ? 'Erre az edzésre már rögzítettük a részvételedet.' : 'Rögzítettük a részvételedet.',
            ];
        });
        abort_if($result === false, 429, 'Egy beküldés már folyamatban van. Próbáld újra egy pillanat múlva.', ['Retry-After' => '1']);

        return $result;
    }

    private function nearestSession(Collection $sessions): ?TrainingSession
    {
        $now = now();

        return $sessions->sort(function (TrainingSession $a, TrainingSession $b) use ($now) {
            $aRunning = $a->starts_at->lte($now) && $a->ends_at->gt($now);
            $bRunning = $b->starts_at->lte($now) && $b->ends_at->gt($now);

            return ($bRunning <=> $aRunning)
                ?: (abs($a->starts_at->diffInSeconds($now)) <=> abs($b->starts_at->diffInSeconds($now)))
                ?: ($b->starts_at <=> $a->starts_at)
                ?: ($a->id <=> $b->id);
        })->first();
    }

    private function checkInKey(Session $browser): string
    {
        if (! $browser->has('check_in_browser_id')) {
            $browser->put('check_in_browser_id', (string) Str::uuid());
        }

        return 'attendance-check-in:'.hash('sha256', $browser->get('check_in_browser_id'));
    }
}
