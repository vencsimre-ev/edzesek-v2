<?php

namespace Tests\Feature;

use App\Actions\AuthenticateWithGoogle;
use App\Actions\Fortify\CreateNewUser;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class ParticipantRegistrationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withHeader('Origin', 'http://127.0.0.1:8000');
    }

    private function guest(array $data = []): Participant
    {
        return Participant::create(array_merge(['name' => 'Minta Vendég', 'email' => 'guest@example.test'], $data));
    }

    public function test_normal_registration_creates_participant_without_matching_guest_or_elevating_privileges(): void
    {
        $guest = $this->guest(['name' => 'Minta Tanuló', 'email' => 'student@example.test']);
        $this->post('/register', [
            'name' => 'Minta Tanuló', 'email' => 'student@example.test', 'password' => 'password',
            'password_confirmation' => 'password', 'role' => 'admin', 'approval_status' => 'approved',
        ])->assertRedirect();
        $user = User::sole();
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);
        $this->assertNull($user->email_verified_at);
        $this->assertSame($user->name, $user->participant->name);
        $this->assertSame($user->email, $user->participant->email);
        $this->assertSame($user->id, $user->participant->user->id);
        $this->assertNotNull($user->participant->created_at);
        $this->assertNotNull($user->participant->updated_at);
        $this->assertNull($guest->fresh()->user_id);
        $this->assertDatabaseCount('participants', 2);
    }

    public function test_google_registration_creates_one_participant_and_returning_login_does_not_sync_or_duplicate(): void
    {
        $google = GoogleUser::fake(['id' => 'sample-google', 'name' => 'Minta Google', 'email' => 'google@example.test', 'email_verified' => true]);
        $action = app(AuthenticateWithGoogle::class);
        $user = $action->authenticate($google);
        $this->assertSame($user->email, $user->participant->email);
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);
        $user->participant->update(['name' => 'Másik név']);
        $this->assertSame($user->id, $action->authenticate($google)->id);
        $this->assertDatabaseCount('participants', 1);
        $this->assertSame('Másik név', $user->participant->fresh()->name);
    }

    public function test_both_registration_actions_roll_back_user_if_participant_creation_fails(): void
    {
        Participant::creating(fn () => throw new \RuntimeException('Simulated failure'));
        try {
            foreach (['normal', 'google'] as $kind) {
                try {
                    if ($kind === 'normal') {
                        app(CreateNewUser::class)->create(['name' => 'Minta', 'email' => 'normal@example.test',
                            'password' => 'password', 'password_confirmation' => 'password']);
                    } else {
                        app(AuthenticateWithGoogle::class)->authenticate(GoogleUser::fake([
                            'id' => 'rollback-google', 'name' => 'Minta', 'email' => 'google@example.test', 'email_verified' => true,
                        ]));
                    }
                    $this->fail('Participant failure must abort registration.');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('Simulated failure', $exception->getMessage());
                }
                $this->assertDatabaseCount('users', 0);
                $this->assertDatabaseCount('participants', 0);
                Notification::assertNothingSent();
            }
        } finally {
            Participant::flushEventListeners();
        }
    }

    public function test_backfill_is_idempotent_and_preserves_existing_and_archived_participants(): void
    {
        $missing = User::factory()->create();
        $existing = User::factory()->approved()->create();
        $archived = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $existing->participant()->create(['name' => 'Megőrzött név']);
        $archived->participant()->create(['name' => 'Archivált'])->delete();
        $this->artisan('app:backfill-participants')->expectsOutput('Létrehozott résztvevők: 1.')->assertSuccessful();
        $this->artisan('app:backfill-participants')->expectsOutput('Létrehozott résztvevők: 0.')->assertSuccessful();
        $this->assertSame($missing->email, $missing->participant->email);
        $this->assertSame('Megőrzött név', $existing->participant->name);
        $this->assertNull($admin->participant);
        $this->assertDatabaseCount('participants', 3);
    }
}
