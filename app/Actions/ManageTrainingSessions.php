<?php

namespace App\Actions;

use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ManageTrainingSessions
{
    public function defaults(): TrainingSession
    {
        $now = CarbonImmutable::now('Europe/Budapest');
        $startsAt = $now->startOfWeek(CarbonImmutable::MONDAY)->addDays(3)->setTime(18, 0);
        if ($startsAt->lt($now)) {
            $startsAt = $startsAt->addWeek();
        }

        return new TrainingSession([
            'title' => 'Mászóedzés',
            'location' => 'Fless!Buda boulder',
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addHours(2)->utc(),
        ]);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        return TrainingSession::query()
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['starts_from']), fn ($query) => $query->where('starts_at', '>=', $this->utc($filters['starts_from'])))
            ->when(isset($filters['starts_before']), fn ($query) => $query->where('starts_at', '<', $this->utc($filters['starts_before'])))
            ->orderBy('starts_at')->orderBy('id')
            ->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1)
            ->appends($filters);
    }

    public function create(array $data, User $admin): TrainingSession
    {
        $data = $this->normalizeDates($data);
        $this->validateDuration($data['starts_at'], $data['ends_at']);
        $status = TrainingSessionStatus::from($data['status'] ?? TrainingSessionStatus::Scheduled->value);
        if ($status === TrainingSessionStatus::Cancelled) {
            throw ValidationException::withMessages(['status' => 'Edzés csak tervezett vagy történeti megtartott állapotban hozható létre.']);
        }
        if ($status === TrainingSessionStatus::Completed && CarbonImmutable::parse($data['ends_at'], 'UTC')->gte(now())) {
            throw ValidationException::withMessages(['status' => 'Történeti megtartott edzés csak múltbeli befejezéssel rögzíthető.']);
        }

        $trainingSession = new TrainingSession;
        $trainingSession->fill(array_intersect_key($data, array_flip(['title', 'location', 'starts_at', 'ends_at'])));
        $trainingSession->status = $status;
        $trainingSession->created_by = $admin->id;
        $trainingSession->save();

        return $trainingSession;
    }

    public function update(TrainingSession $trainingSession, array $data): TrainingSession
    {
        $data = $this->normalizeDates(array_intersect_key($data, array_flip(['title', 'location', 'starts_at', 'ends_at'])));
        if (isset($data['starts_at'], $data['ends_at'])) {
            $this->validateDuration($data['starts_at'], $data['ends_at']);
        }

        return DB::transaction(function () use ($trainingSession, $data) {
            // First statement is a write, not a SELECT followed by an assumed row lock.
            // The status and current opposite time bound are checked atomically by SQLite.
            $query = TrainingSession::query()->whereKey($trainingSession->id)->where('status', TrainingSessionStatus::Scheduled);
            if (isset($data['starts_at']) && ! isset($data['ends_at'])) {
                $query->where('ends_at', '>', $data['starts_at']);
            }
            if (isset($data['ends_at']) && ! isset($data['starts_at'])) {
                $query->where('starts_at', '<', $data['ends_at']);
            }
            $changed = $query->update($data + ['updated_at' => $this->utc(now()->toISOString())]);
            $current = TrainingSession::query()->findOrFail($trainingSession->id);
            if (! $changed) {
                if ($current->status !== TrainingSessionStatus::Scheduled) {
                    throw new ConflictHttpException('Csak tervezett edzés adatai módosíthatók.');
                }
                $this->validateDuration($data['starts_at'] ?? $current->getRawOriginal('starts_at'), $data['ends_at'] ?? $current->getRawOriginal('ends_at'));
            }

            return $current;
        }, 5);
    }

    public function complete(TrainingSession $trainingSession): TrainingSession
    {
        return DB::transaction(function () use ($trainingSession) {
            TrainingSession::query()->whereKey($trainingSession->id)
                ->where('status', TrainingSessionStatus::Scheduled)
                ->where('ends_at', '<=', $this->utc(now()->toISOString()))
                ->update(['status' => TrainingSessionStatus::Completed->value]);
            $current = TrainingSession::query()->findOrFail($trainingSession->id);
            if ($current->status === TrainingSessionStatus::Cancelled) {
                throw new ConflictHttpException('Elmaradt edzés nem jelölhető megtartottnak.');
            }
            if ($current->status === TrainingSessionStatus::Scheduled) {
                throw ValidationException::withMessages(['ends_at' => 'Az edzés csak a befejezési időpont elérése után jelölhető megtartottnak.']);
            }

            return $current;
        }, 5);
    }

    public function cancel(TrainingSession $trainingSession, ?string $reason = null): TrainingSession
    {
        return DB::transaction(function () use ($trainingSession, $reason) {
            TrainingSession::query()->whereKey($trainingSession->id)->where('status', TrainingSessionStatus::Scheduled)
                ->update(['status' => TrainingSessionStatus::Cancelled->value, 'cancellation_reason' => $reason]);
            $current = TrainingSession::query()->findOrFail($trainingSession->id);
            if ($current->status === TrainingSessionStatus::Completed) {
                throw new ConflictHttpException('Megtartott edzés nem jelölhető elmaradtnak.');
            }

            return $current;
        }, 5);
    }

    public function delete(TrainingSession $trainingSession): void
    {
        DB::transaction(function () use ($trainingSession) {
            abort_unless(TrainingSession::query()->whereKey($trainingSession->id)->delete(), 404);
        }, 5);
    }

    private function normalizeDates(array $data): array
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->utc($data[$field]);
            }
        }

        return $data;
    }

    private function utc(string $value): string
    {
        return CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u');
    }

    private function validateDuration(string $startsAt, string $endsAt): void
    {
        if ($startsAt >= $endsAt) {
            throw ValidationException::withMessages(['ends_at' => 'A befejezésnek a kezdési időpont után kell lennie.']);
        }
    }
}
