<?php

namespace App\Actions;

use App\Models\Participant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ManageParticipants
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return Participant::query()->when($search !== null && $search !== '', function ($query) use ($search) {
            // instr treats %, _ and backslashes as literal search characters in SQLite.
            $query->where(function ($query) use ($search) {
                foreach (['name', 'email', 'phone'] as $column) {
                    $query->orWhereRaw("instr(lower({$column}), lower(?)) > 0", [$search]);
                }
            });
        })->orderBy('id')->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1)
            ->appends($filters);
    }

    public function create(array $data): Participant
    {
        return Participant::create($data);
    }

    public function update(Participant $participant, array $data): Participant
    {
        $participant->update($data);

        return $participant;
    }

    public function archive(Participant $participant): void
    {
        $participant->delete();
    }
}
