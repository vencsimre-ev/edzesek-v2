<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\Attendance;
use App\Models\Participant;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 18:30', 'Europe/Budapest'));
        $this->admin = User::factory()->admin()->create();
        $this->withHeader('Origin', 'http://127.0.0.1:8000');
    }

    private function training(string $start = '2026-11-05 18:00', string $end = '2026-11-05 20:00', array $extra = []): TrainingSession
    {
        return TrainingSession::factory()->create($extra + [
            'created_by' => $this->admin->id,
            'starts_at' => CarbonImmutable::parse($start, 'Europe/Budapest')->utc(),
            'ends_at' => CarbonImmutable::parse($end, 'Europe/Budapest')->utc(),
            'location' => 'Fless!Buda boulder',
        ]);
    }

    private function guest(TrainingSession $session): array
    {
        return ['training_session_id' => $session->id, 'name' => 'Minta Vendég', 'submission_id' => (string) Str::uuid()];
    }

    private function student(): User
    {
        $user = User::factory()->approved()->create();
        $participant = Participant::create(['name' => $user->name, 'notes' => 'PRIVATE_SAMPLE_NOTE']);
        $participant->user()->associate($user);
        $participant->save();

        return $user;
    }

    public function test_current_session_wins_and_choices_are_limited_and_safe(): void
    {
        $current = $this->training();
        $nearerStart = $this->training('2026-11-05 18:20', '2026-11-05 18:25');
        $cancelled = $this->training('2026-11-06 18:00', '2026-11-06 20:00', ['status' => TrainingSessionStatus::Cancelled]);
        $old = $this->training('2026-10-28 18:00', '2026-10-28 20:00');
        $far = $this->training('2027-01-01 18:00', '2027-01-01 20:00');
        $response = $this->getJson('/api/v1/check-ins')->assertOk()
            ->assertJsonPath('data.training_session.id', $current->id)->assertJsonCount(2, 'data.training_sessions');
        $this->assertSame([$current->id, $nearerStart->id], array_column($response->json('data.training_sessions'), 'id'));
        $this->assertSame(['id', 'date', 'starts_at', 'ends_at', 'location'], array_keys($response->json('data.training_session')));
        $this->get('/edzesre-jottem')->assertOk()->assertSee('Edzésre jöttem')->assertSee('Fless!Buda boulder');
        foreach ([$cancelled, $old, $far] as $invalid) {
            $this->getJson('/api/v1/check-ins?training_session_id='.$invalid->id)->assertUnprocessable();
            $this->postJson('/api/v1/check-ins', $this->guest($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_public_landing_lists_database_sessions_and_links_to_the_selected_check_in(): void
    {
        $current = $this->training();
        $next = $this->training('2026-11-12 18:00', '2026-11-12 20:00', ['location' => 'Minta következő helyszín']);
        $cancelled = $this->training('2026-11-19 18:00', '2026-11-19 20:00', [
            'status' => TrainingSessionStatus::Cancelled, 'cancellation_reason' => 'PRIVATE_SAMPLE_REASON',
        ]);
        $this->training('2026-11-04 18:00', '2026-11-04 20:00', ['location' => 'Régi minta helyszín']);
        $this->training('2027-02-04 18:00', '2027-02-04 20:00', ['location' => 'Távoli minta helyszín']);

        $link = route('check-in.index', ['training_session_id' => $next->id]);
        $this->get('/')->assertOk()->assertSee('Mászóedzések a Fless!Budában')->assertSee('Rólam')
            ->assertSee('Minta következő helyszín')->assertSee('Elmarad')->assertSee($link)
            ->assertSee(route('login'))->assertSee('images/vencsimi.jpg')
            ->assertDontSee(route('check-in.index', ['training_session_id' => $cancelled->id]))
            ->assertDontSee('PRIVATE_SAMPLE_REASON')->assertDontSee('Régi minta helyszín')->assertDontSee('Távoli minta helyszín')
            ->assertViewHas('sessions', fn ($sessions) => $sessions->pluck('id')->all() === [$current->id, $next->id, $cancelled->id]);
        $this->get($link)->assertOk()->assertViewHas('selected', fn ($selected) => $selected->is($next))
            ->assertSee('value="'.$next->id.'" selected', false);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_public_landing_is_available_without_sessions_and_to_restricted_accounts(): void
    {
        $this->get('/')->assertOk()->assertSee('Jelenleg nincs meghirdetett edzés.')->assertSee(route('login'));
        $this->actingAs(User::factory()->create(), 'web');
        $this->get('/')->assertOk()->assertSee('Mászóedzések a Fless!Budában')->assertSee(route('logout'));
        $this->get('/edzesre-jottem')->assertRedirect(route('approval.pending'));
    }

    public function test_nearest_start_and_later_tie_break_and_manual_selection(): void
    {
        $before = $this->training('2026-11-05 17:30', '2026-11-05 18:00');
        $after = $this->training('2026-11-05 19:30', '2026-11-05 20:00');
        $this->getJson('/api/v1/check-ins')->assertJsonPath('data.training_session.id', $after->id);
        $this->travel(-10)->minutes();
        $this->getJson('/api/v1/check-ins')->assertJsonPath('data.training_session.id', $before->id);
        $this->travel(20)->minutes();
        $this->getJson('/api/v1/check-ins')->assertJsonPath('data.training_session.id', $after->id);
        $this->getJson('/api/v1/check-ins?training_session_id='.$before->id)->assertJsonPath('data.training_session.id', $before->id);
        $this->get('/edzesre-jottem?training_session_id='.$before->id)->assertViewHas('selected', fn ($session) => $session->is($before));
        $this->postJson('/api/v1/check-ins', $this->guest($before))->assertCreated()->assertJsonPath('data.training_session.id', $before->id);
    }

    public function test_empty_schedule_is_clear_and_does_not_show_a_submit_form(): void
    {
        $this->get('/edzesre-jottem')->assertOk()->assertSee('Most nincs választható edzés.')->assertDontSee('id="check-in-form"', false);
        $this->getJson('/api/v1/check-ins')->assertOk()->assertJsonPath('data.training_session', null)->assertJsonCount(0, 'data.training_sessions');
        $this->postJson('/api/v1/check-ins', ['training_session_id' => 999, 'name' => 'Minta'])->assertUnprocessable();
    }

    public function test_guest_creates_separate_person_with_null_creator_and_no_account(): void
    {
        $session = $this->training();
        $existing = Participant::create(['name' => 'Minta Vendég', 'email' => 'sample@example.test', 'notes' => 'PRIVATE_SAMPLE_NOTE']);
        $payload = $this->guest($session);
        $this->get('/edzesre-jottem');
        $this->postJson('/api/v1/check-ins', $payload)->assertCreated()
            ->assertJsonPath('data.message', 'Rögzítettük a részvételedet.')
            ->assertJsonPath('data.training_session.date', '2026-11-05')
            ->assertJsonPath('data.retry_after', 60)->assertDontSee('PRIVATE_SAMPLE_NOTE')->assertDontSee('sample@example.test');
        $attendance = Attendance::sole();
        $this->assertNull($attendance->created_by);
        $this->assertNotSame($existing->id, $attendance->participant_id);
        $this->assertNull($attendance->participant->user_id);
        $this->assertNull($attendance->participant->email);
        $this->assertNull($attendance->participant->phone);
        $this->assertDatabaseCount('users', 1);
        $this->getJson('/api/v1/check-ins')->assertDontSee('PRIVATE_SAMPLE_NOTE')->assertJsonMissingPath('data.attendances');
        $this->travel(61)->seconds();
        $this->postJson('/api/v1/check-ins', $payload)->assertConflict();
        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_logged_in_user_uses_only_their_own_participant_and_repeat_is_harmless(): void
    {
        $session = $this->training();
        $student = $this->student();
        $this->actingAs($student, 'web');
        $this->get('/edzesre-jottem')->assertOk()->assertSee($student->name)->assertDontSee('id="check-in-name"', false);
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id])->assertCreated();
        $this->assertDatabaseHas('attendances', ['training_session_id' => $session->id, 'participant_id' => $student->participant->id, 'created_by' => $student->id]);
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id])->assertOk()
            ->assertJsonPath('data.already_recorded', true)->assertJsonPath('data.message', 'Erre az edzésre már rögzítettük a részvételedet.');
        $this->get('/edzesre-jottem')->assertSee('Erre az edzésre már rögzítettük a részvételedet.');
        foreach (['participant_id' => $this->admin->id, 'name' => 'Másik Minta', 'created_by' => $this->admin->id, 'submission_id' => (string) Str::uuid()] as $field => $value) {
            $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_cooldown_is_shared_by_web_and_api_but_separate_for_other_browsers(): void
    {
        $session = $this->training();
        $this->get('/edzesre-jottem');
        $this->post('/edzesre-jottem', $this->guest($session))->assertRedirect('/edzesre-jottem?training_session_id='.$session->id)
            ->assertSessionHas('message', 'Rögzítettük a részvételedet. 2026. 11. 05.');
        $this->get('/edzesre-jottem')->assertSee('data-check-in-cooldown="60"', false);
        $this->postJson('/api/v1/check-ins', $this->guest($session))->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->travel(59)->seconds();
        $this->postJson('/api/v1/check-ins', $this->guest($session))->assertStatus(429);
        $this->travel(1)->seconds();
        $this->postJson('/api/v1/check-ins', $this->guest($session))->assertCreated();
        $this->assertDatabaseCount('attendances', 2);
        $this->app['session']->driver()->invalidate();
        $this->get('/edzesre-jottem');
        $this->postJson('/api/v1/check-ins', $this->guest($session))->assertCreated();
        $this->assertDatabaseCount('attendances', 3);
    }

    public function test_invalid_input_does_not_start_cooldown_and_guest_cannot_choose_someone_else(): void
    {
        $session = $this->training();
        $this->get('/edzesre-jottem');
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id, 'name' => '  ', 'submission_id' => (string) Str::uuid()])->assertUnprocessable();
        $this->postJson('/api/v1/check-ins', $this->guest($session) + ['participant_id' => 123])->assertUnprocessable();
        $this->getJson('/api/v1/check-ins')->assertJsonPath('data.retry_after', 0);
        $this->postJson('/api/v1/check-ins', $this->guest($session))->assertCreated();
        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_restricted_accounts_cannot_fall_back_to_guest_check_in(): void
    {
        $session = $this->training();
        foreach ([User::factory()->create(), User::factory()->unverified()->approved()->create(),
            User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])] as $user) {
            Auth::forgetGuards();
            $this->actingAs($user, 'web');
            $redirect = ! $user->hasVerifiedEmail() ? route('verification.notice') : route('approval.pending');
            $this->get('/edzesre-jottem')->assertRedirect($redirect);
            $this->post('/edzesre-jottem', $this->guest($session))->assertRedirect($redirect);
            $this->getJson('/api/v1/check-ins')->assertForbidden();
            $this->postJson('/api/v1/check-ins', $this->guest($session))->assertForbidden();
        }
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_missing_or_archived_own_participant_is_not_replaced_by_a_guest(): void
    {
        $session = $this->training();
        $this->actingAs(User::factory()->approved()->create(), 'web');
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id])->assertForbidden();
        $student = $this->student();
        $student->participant->delete();
        $this->actingAs($student, 'web');
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id])->assertForbidden();
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_trainer_can_check_in_and_gets_their_own_participant_only_once(): void
    {
        $session = $this->training();
        $sameName = Participant::create(['name' => $this->admin->name]);
        $this->actingAs($this->admin, 'web');
        $this->post('/edzesre-jottem', ['training_session_id' => $session->id])->assertRedirect()->assertSessionHasNoErrors();
        $participant = $this->admin->participant()->sole();
        $this->assertNotSame($sameName->id, $participant->id);
        $this->assertSame($this->admin->name, $participant->name);
        $this->assertDatabaseHas('attendances', ['training_session_id' => $session->id,
            'participant_id' => $participant->id, 'created_by' => $this->admin->id]);
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id])->assertOk()
            ->assertJsonPath('data.already_recorded', true);
        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseCount('attendances', 1);
        $participant->delete();
        $this->postJson('/api/v1/check-ins', ['training_session_id' => $session->id])->assertForbidden();
        $this->assertDatabaseCount('participants', 2);
    }

    public function test_public_access_does_not_open_admin_endpoints_and_all_writes_require_csrf(): void
    {
        $session = $this->training();
        $this->getJson('/api/v1/training-sessions/'.$session->id.'/attendances')->assertUnauthorized();
        $this->postJson('/api/v1/training-sessions/'.$session->id.'/attendances', $this->guest($session))->assertUnauthorized();
        $this->app['env'] = 'local';
        $this->post('/edzesre-jottem', $this->guest($session))->assertStatus(419);
        $this->postJson('/api/v1/check-ins', $this->guest($session))->assertStatus(419);
        $this->assertDatabaseCount('attendances', 0);
        $this->withSession(['_token' => 'sample-csrf-token']);
        $this->postJson('/api/v1/check-ins', $this->guest($session) + ['_token' => 'sample-csrf-token'])->assertCreated();
        $this->assertDatabaseCount('attendances', 1);
    }
}
