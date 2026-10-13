<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LinkParticipantUser
{
    public function link(Participant $participant, int $userId): Participant
    {
        try {
            return DB::transaction(function () use ($participant, $userId) {
                $user = User::query()->lockForUpdate()->findOrFail($userId);
                $current = Participant::query()->lockForUpdate()->findOrFail($participant->id);
                if ($user->role !== Role::Student) {
                    throw ValidationException::withMessages(['user_id' => 'Csak student fiók kapcsolható résztvevőhöz.']);
                }
                if ($current->user_id === $user->id) {
                    return $current;
                }
                if ($current->user_id !== null || Participant::withTrashed()->where('user_id', $user->id)->exists()) {
                    throw new ConflictHttpException('A résztvevő vagy a fiók már össze van kapcsolva. Automatikus összevonás nem történik.');
                }
                // Conditional update also prevents concurrent reassignment of the same guest.
                $changed = Participant::query()->whereKey($current->id)->whereNull('user_id')
                    ->update([
                        'user_id' => $user->id,
                        'email' => filled($current->email) ? $current->email : $user->email,
                        'updated_at' => now(),
                    ]);
                if (! $changed) {
                    throw new ConflictHttpException('A résztvevő már össze van kapcsolva.');
                }

                return $current->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw new ConflictHttpException('A fiók már másik résztvevőhöz kapcsolódik.');
        }
    }
}
