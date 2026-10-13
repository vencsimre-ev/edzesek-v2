<?php

namespace App\Console\Commands;

use App\Actions\EnsureStudentParticipant;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillParticipants extends Command
{
    protected $signature = 'app:backfill-participants';

    protected $description = 'A hiányzó student résztvevőrekordok pótlása';

    public function handle(EnsureStudentParticipant $participants): int
    {
        $count = 0;
        User::query()->where('role', Role::Student)->chunkById(100, function ($users) use ($participants, &$count) {
            foreach ($users as $user) {
                $participant = DB::transaction(fn () => $participants->ensure($user));
                $count += (int) $participant->wasRecentlyCreated;
            }
        });
        $this->info("Létrehozott résztvevők: {$count}.");

        return self::SUCCESS;
    }
}
