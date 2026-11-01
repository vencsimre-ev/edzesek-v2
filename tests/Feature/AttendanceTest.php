<?php

namespace Tests\Feature;

use App\Actions\ManageAttendances;
use App\Actions\ManageTrainingSessions;
use App\Enums\ApprovalStatus;
use App\Enums\AttendanceEventType;
use App\Enums\TrainingSessionStatus;
use App\Models\Attendance;
use App\Models\AttendanceEvent;
use App\Models\Participant;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin, 'web');
        $this->withHeader('Origin', 'http://127.0.0.1:8000');
    }

    private function training(TrainingSessionStatus $status = TrainingSessionStatus::Scheduled): TrainingSession
    {
        return TrainingSession::factory()->create(['created_by' => $this->admin->id, 'status' => $status,
            'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour()]);
    }

    private function participant(string $name = 'Minta Résztvevő'): Participant
    {
        return Participant::create(['name' => $name, 'notes' => 'INTERNAL_SAMPLE_NOTE']);
    }

    private function url(TrainingSession $training): string
    {
        return '/api/v1/training-sessions/'.$training->id.'/attendances';
    }

    private function add(TrainingSession $training, Participant $participant): Attendance
    {
        return app(ManageAttendances::class)->add($training, ['participant_id' => $participant->id], $this->admin);
    }

    public function test_existing_participant_is_added_with_server_creator_and_explicit_safe_resource(): void
    {
        $training = $this->training();
        $participant = $this->participant();
        $response = $this->postJson($this->url($training), ['participant_id' => $participant->id])->assertCreated()
            ->assertJsonPath('data.participant_name', 'Minta Résztvevő')->assertJsonPath('data.training_session_id', $training->id)
            ->assertJsonPath('data.created_by', $this->admin->id)->assertDontSee('INTERNAL_SAMPLE_NOTE');
        $this->assertSame(['id', 'training_session_id', 'participant_id', 'participant_name', 'created_by', 'withdrawn_at', 'created_at', 'updated_at'],
            array_keys($response->json('data')));
        $attendance = Attendance::sole();
        $this->assertNotNull($attendance->created_at);
        $this->assertNotNull($attendance->updated_at);
        $this->assertTrue($attendance->trainingSession->is($training));
        $this->assertTrue($attendance->creator->is($this->admin));
        $this->getJson($this->url($training))->assertOk()->assertJsonPath('meta.total', 1)->assertDontSee('INTERNAL_SAMPLE_NOTE');
    }

    public function test_rosters_are_separate_and_paginated_with_validated_parameters(): void
    {
        $first = $this->training();
        $second = $this->training();
        for ($index = 0; $index < 3; $index++) {
            $this->add($first, $this->participant('Első edzés '.$index));
        }
        $this->add($second, $this->participant('Második edzés'));
        $this->getJson($this->url($first).'?per_page=2')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)->assertDontSee('Második edzés');
        $this->getJson($this->url($first).'?per_page=2&page=2')->assertJsonCount(1, 'data');
        $this->getJson($this->url($second))->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.participant_name', 'Második edzés');
        $this->getJson($this->url($first).'?per_page=101&page=0')->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page']);
    }

    public function test_guest_needs_only_name_and_submission_id_and_never_merges_identical_names(): void
    {
        $training = $this->training();
        $existing = $this->participant('Minta Vendég');
        $payload = ['name' => 'Minta Vendég', 'submission_id' => (string) Str::uuid()];
        $this->postJson($this->url($training), $payload)->assertCreated();
        $guest = Participant::orderByDesc('id')->firstOrFail();
        $this->assertNotSame($existing->id, $guest->id);
        $this->assertNull($guest->user_id);
        $this->assertNull($guest->email);
        $this->assertNull($guest->phone);
        $this->assertNull($guest->notes);
        $this->assertDatabaseCount('users', 1);
        $this->postJson($this->url($training), $payload)->assertConflict();
        $this->assertDatabaseCount('participants', 2);
        $this->assertDatabaseCount('attendances', 1);
        $this->postJson($this->url($training), ['name' => 'Minta Vendég', 'submission_id' => (string) Str::uuid()])->assertCreated();
        $this->assertDatabaseCount('participants', 3);
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_duplicate_participant_returns_409_but_can_attend_another_session(): void
    {
        $training = $this->training();
        $participant = $this->participant();
        $this->postJson($this->url($training), ['participant_id' => $participant->id])->assertCreated();
        $this->postJson($this->url($training), ['participant_id' => $participant->id])->assertConflict()->assertJsonStructure(['message']);
        $this->postJson($this->url($this->training()), ['participant_id' => $participant->id])->assertCreated();
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('attendances', 2);
    }

    public function test_post_requires_exactly_existing_participant_or_guest_and_protects_creator(): void
    {
        $training = $this->training();
        $participant = $this->participant();
        foreach ([[], ['participant_id' => $participant->id, 'name' => 'Kétértelmű'],
            ['participant_id' => $participant->id, 'name' => null],
            ['participant_id' => null, 'name' => 'Kétértelmű', 'submission_id' => (string) Str::uuid()],
            ['name' => 'Minta vendég'], ['name' => '', 'submission_id' => (string) Str::uuid()],
            ['name' => str_repeat('x', 256), 'submission_id' => (string) Str::uuid()],
            ['name' => 'Minta', 'submission_id' => 'invalid'],
            ['participant_id' => $participant->id, 'submission_id' => (string) Str::uuid()],
            ['participant_id' => $participant->id, 'created_by' => $this->admin->id],
            ['participant_id' => 999999]] as $payload) {
            $this->postJson($this->url($training), $payload)->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('participants', 1);
    }

    public function test_scheduled_and_completed_rosters_can_be_corrected_without_deleting_participant(): void
    {
        $participant = $this->participant();
        foreach ([TrainingSessionStatus::Scheduled, TrainingSessionStatus::Completed] as $status) {
            $training = $this->training($status);
            $created = $this->postJson($this->url($training), ['participant_id' => $participant->id])->assertCreated();
            $this->deleteJson($this->url($training).'/'.$created->json('data.id'))->assertNoContent();
            $this->getJson($this->url($training))->assertJsonPath('meta.total', 0);
            $this->assertDatabaseHas('participants', ['id' => $participant->id, 'deleted_at' => null]);
            $this->assertSame($status, $training->fresh()->status);
        }
    }

    public function test_cancelled_session_rejects_existing_and_guest_addition_without_side_effects(): void
    {
        $training = $this->training(TrainingSessionStatus::Cancelled);
        $participant = $this->participant();
        $this->postJson($this->url($training), ['participant_id' => $participant->id])->assertConflict();
        $this->postJson($this->url($training), ['name' => 'Tiltott vendég', 'submission_id' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_stale_session_cannot_allow_addition_after_cancellation(): void
    {
        $training = $this->training();
        app(ManageTrainingSessions::class)->cancel($training);
        try {
            app(ManageAttendances::class)->add($training, ['name' => 'Tiltott vendég', 'submission_id' => (string) Str::uuid()], $this->admin);
            $this->fail('The current cancelled state must be checked.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseCount('participants', 0);
            $this->assertDatabaseCount('attendances', 0);
        }
    }

    public function test_sessions_with_attendance_cannot_be_cancelled_or_physically_deleted(): void
    {
        $training = $this->training();
        $attendance = $this->add($training, $this->participant());
        $this->postJson('/api/v1/training-sessions/'.$training->id.'/cancel', ['cancellation_reason' => 'Tiltott'])
            ->assertConflict()->assertJsonPath('message', fn ($message) => str_contains($message, 'névsort'));
        $this->deleteJson('/api/v1/training-sessions/'.$training->id)->assertConflict();
        $this->assertSame(TrainingSessionStatus::Scheduled, $training->fresh()->status);
        $this->assertNull($training->fresh()->cancellation_reason);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
        $this->deleteJson($this->url($training).'/'.$attendance->id)->assertNoContent();
        $this->postJson('/api/v1/training-sessions/'.$training->id.'/cancel')->assertOk();
        $this->deleteJson('/api/v1/training-sessions/'.$training->id)->assertNoContent();
        $this->assertDatabaseCount('participants', 1);
    }

    public function test_wrong_parent_cannot_remove_another_sessions_attendance_in_web_or_api(): void
    {
        $first = $this->training();
        $second = $this->training();
        $attendance = $this->add($second, $this->participant());
        $this->deleteJson($this->url($first).'/'.$attendance->id)->assertNotFound();
        $this->delete('/admin/training-sessions/'.$first->id.'/attendances/'.$attendance->id)->assertNotFound();
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
    }

    public function test_archived_participant_remains_visible_in_history_but_cannot_be_added_again(): void
    {
        $training = $this->training();
        $participant = $this->participant('Archivált Minta');
        $this->add($training, $participant);
        $participant->delete();
        $this->getJson($this->url($training))->assertOk()->assertJsonPath('data.0.participant_name', 'Archivált Minta')
            ->assertDontSee('INTERNAL_SAMPLE_NOTE');
        $this->get('/admin/training-sessions/'.$training->id)->assertOk()->assertSee('Archivált Minta')->assertSee('Archivált');
        $this->postJson($this->url($this->training()), ['participant_id' => $participant->id])->assertUnprocessable();
    }

    public function test_guest_and_attendance_are_rolled_back_together_if_saving_attendance_fails(): void
    {
        $training = $this->training();
        Attendance::creating(fn () => throw new \RuntimeException('Simulated failure'));
        try {
            try {
                app(ManageAttendances::class)->add($training, ['name' => 'Visszagörgetett vendég', 'submission_id' => (string) Str::uuid()], $this->admin);
                $this->fail('Saving must fail.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Simulated failure', $exception->getMessage());
            }
            $this->assertDatabaseCount('participants', 0);
            $this->assertDatabaseCount('attendances', 0);
        } finally {
            Attendance::flushEventListeners();
        }
    }

    public function test_database_constraints_protect_duplicates_and_training_history(): void
    {
        $training = $this->training();
        $attendance = $this->add($training, $this->participant());
        try {
            $attendance->replicate()->save();
            $this->fail('Duplicate attendance must fail.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('attendances', 1);
        }
        try {
            DB::table('training_sessions')->where('id', $training->id)->delete();
            $this->fail('Foreign key must protect attendance history.');
        } catch (QueryException) {
            $this->assertDatabaseHas('training_sessions', ['id' => $training->id]);
        }
    }

    public function test_web_roster_searches_active_names_excludes_existing_attendees_and_shows_count(): void
    {
        $training = $this->training();
        $chosen = $this->participant('Minta Anna');
        $other = $this->participant('Minta Béla');
        $archived = $this->participant('Archivált Anna');
        $archived->delete();
        $base = '/admin/training-sessions/'.$training->id;
        $response = $this->get($base.'?participant_search=Anna')->assertOk()->assertSee('Résztvevők (0 fő)');
        $this->assertSame([$chosen->id], $response->viewData('participants')->pluck('id')->all());
        $this->post($base.'/attendances', ['participant_id' => $chosen->id])->assertRedirect($base)->assertSessionHasNoErrors();
        $this->get($base)->assertOk()->assertSee('Résztvevők (1 fő)')->assertDontSee('INTERNAL_SAMPLE_NOTE')
            ->assertViewHas('participants', fn ($participants) => $participants->pluck('id')->all() === [$other->id]);
        $this->post($base.'/attendances', ['participant_id' => $chosen->id])->assertRedirect($base)->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('attendances', 1);
        $this->from($base)->get($base.'?participant_search[]=invalid')->assertSessionHasErrors('participant_search');
    }

    public function test_training_list_shows_names_and_counts_including_archived_participants(): void
    {
        $training = $this->training();
        $empty = $this->training();
        $archived = $this->participant('Korábbi Minta Vendég');
        $this->add($training, $archived);
        $this->add($training, $this->participant('Másik Minta Vendég'));
        $archived->delete();

        $response = $this->get('/admin/training-sessions')->assertOk()
            ->assertSee('Korábbi Minta Vendég')->assertSee('Másik Minta Vendég')
            ->assertSee('2 fő')->assertSee('0 fő')->assertDontSee('INTERNAL_SAMPLE_NOTE');
        $sessions = $response->viewData('sessions')->getCollection()->keyBy('id');
        $this->assertCount(2, $sessions[$training->id]->attendances);
        $this->assertCount(0, $sessions[$empty->id]->attendances);
    }

    public function test_existing_participant_picker_stays_open_after_addition_and_keeps_search(): void
    {
        $training = $this->training();
        $base = '/admin/training-sessions/'.$training->id;
        $first = $this->participant('Minta Anna');
        $second = $this->participant('Minta Béla');

        $this->post($base.'/attendances', ['participant_id' => $first->id])
            ->assertRedirect($base)->assertSessionHas('participant_picker_open', true);
        $html = $this->get($base)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<details id="participant-picker"\s+open\s+class="mb-3">/', $html);

        $this->post($base.'/attendances', ['participant_id' => $second->id, 'participant_search' => 'Minta'])
            ->assertRedirect($base.'?participant_search=Minta')->assertSessionHasNoErrors();
        $this->get($base.'?participant_search=Minta')->assertOk()
            ->assertSee('value="Minta"', false)->assertSee('Résztvevők (2 fő)');
    }

    public function test_removal_preserves_the_open_or_closed_picker_and_search(): void
    {
        $training = $this->training();
        $base = '/admin/training-sessions/'.$training->id;
        foreach ([true, false] as $open) {
            $attendance = $this->add($training, $this->participant());
            $this->delete($base.'/attendances/'.$attendance->id, [
                'participant_picker_open' => $open ? '1' : '0',
                'participant_search' => 'Minta',
            ])->assertRedirect($base.'?participant_search=Minta')
                ->assertSessionHas('participant_picker_open', $open)->assertSessionHasNoErrors();
            $html = $this->get($base.'?participant_search=Minta')->assertOk()->getContent();
            $this->assertSame($open, (bool) preg_match('/<details id="participant-picker"\s+open\s+class="mb-3">/', $html));
            $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
            $this->assertDatabaseHas('participants', ['id' => $attendance->participant_id]);
        }
    }

    public function test_web_guest_form_has_retry_protection_and_removal_needs_no_confirmation(): void
    {
        $training = $this->training(TrainingSessionStatus::Completed);
        $base = '/admin/training-sessions/'.$training->id;
        $html = $this->get($base)->assertOk()->getContent();
        preg_match('/name="submission_id" value="([^"]+)"/', $html, $match);
        $this->assertTrue(Str::isUuid($match[1]));
        $payload = ['name' => 'Webes Minta Vendég', 'submission_id' => $match[1]];
        $this->post($base.'/attendances', $payload)->assertRedirect($base)->assertSessionHasNoErrors();
        $this->post($base.'/attendances', $payload)->assertRedirect($base)->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('participants', 1);
        $attendance = Attendance::sole();
        $this->get($base)->assertOk()->assertDontSee('name="confirm_remove"', false);
        $this->delete($base.'/attendances/'.$attendance->id)->assertRedirect($base)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseHas('participants', ['id' => $attendance->participant_id, 'deleted_at' => null]);
    }

    public function test_web_training_cancel_and_delete_show_clear_conflict_until_roster_is_cleared(): void
    {
        $training = $this->training();
        $attendance = $this->add($training, $this->participant());
        $base = '/admin/training-sessions/'.$training->id;
        $this->post($base.'/cancel')->assertRedirect($base)->assertSessionHasErrors('training_session');
        $this->delete($base, ['confirm_delete' => 1])->assertRedirect($base)->assertSessionHasErrors('training_session');
        $this->get($base)->assertOk()->assertSee('rendezd a névsort');
        $this->delete($base.'/attendances/'.$attendance->id)->assertRedirect($base);
        $this->post($base.'/cancel')->assertRedirect($base)->assertSessionHasNoErrors();
        $this->get($base)->assertOk()->assertSee('Elmaradt')->assertDontSee('name="participant_id"', false);
        $this->delete($base, ['confirm_delete' => 1])->assertRedirect(route('admin.training-sessions.index'));
    }

    public function test_automatic_completion_keeps_roster_and_allows_later_corrections(): void
    {
        $training = $this->training();
        $attendance = $this->add($training, $this->participant());
        config(['training.cron_key' => 'test-cron-key']);
        $this->withToken('test-cron-key')->postJson('/api/v1/training-sessions/sync-weekly')->assertOk();
        $this->assertSame(TrainingSessionStatus::Completed, $training->fresh()->status);
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
        $this->postJson($this->url($training), ['participant_id' => $this->participant('Utólagos Minta')->id])->assertCreated();
    }

    public function test_guests_students_and_unapproved_or_unverified_users_cannot_access_rosters(): void
    {
        $training = $this->training();
        $participant = $this->participant();
        $attendance = $this->add($training, $participant);
        $api = $this->url($training);
        $web = '/admin/training-sessions/'.$training->id;
        Auth::forgetGuards();
        Auth::guard('web')->logout();
        foreach ([['get', $api], ['post', $api], ['delete', $api.'/'.$attendance->id]] as [$method, $url]) {
            $this->{$method}($url, ['participant_id' => $participant->id])->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        }
        $this->get($web)->assertRedirect(route('login'));
        $this->post($web.'/attendances', ['participant_id' => $participant->id])->assertRedirect(route('login'));
        $this->delete($web.'/attendances/'.$attendance->id)->assertRedirect(route('login'));
        foreach ([User::factory()->approved()->create(), User::factory()->create(), User::factory()->unverified()->create(),
            User::factory()->admin()->unverified()->create(),
            User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])] as $actor) {
            Auth::forgetGuards();
            $this->actingAs($actor, 'web');
            foreach ([['get', $api], ['post', $api], ['delete', $api.'/'.$attendance->id]] as [$method, $url]) {
                $this->{$method}($url, ['participant_id' => $participant->id])->assertForbidden()->assertHeader('Content-Type', 'application/json');
            }
            foreach ([['get', $web], ['post', $web.'/attendances'], ['delete', $web.'/attendances/'.$attendance->id]] as [$method, $url]) {
                $response = $this->{$method}($url, ['participant_id' => $participant->id]);
                if (! $actor->hasVerifiedEmail()) {
                    $response->assertRedirect(route('verification.notice'));
                } elseif (! $actor->isApproved()) {
                    $response->assertRedirect(route('approval.pending'));
                } else {
                    $response->assertForbidden();
                }
            }
        }
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
        $this->assertDatabaseCount('attendances', 1);
    }

    private function withdrawnAttendance(TrainingSession $training): Attendance
    {
        $student = User::factory()->approved()->create();
        $participant = $this->participant('Visszaállítható Minta');
        $participant->user()->associate($student);
        $participant->save();
        $attendance = $this->add($training, $participant);
        app(ManageAttendances::class)->withdraw(['training_session_id' => $training->id], $student, session()->driver());

        return $attendance->fresh();
    }

    public function test_trainer_restores_the_same_attendance_and_history_is_safe_and_idempotent(): void
    {
        $training = $this->training();
        $attendance = $this->withdrawnAttendance($training);
        $student = $attendance->participant->user;
        $createdAt = $attendance->created_at;
        $base = '/admin/training-sessions/'.$training->id;
        $api = $this->url($training).'/'.$attendance->id.'/restore';
        $this->assertSame(AttendanceEventType::Withdrawn, AttendanceEvent::sole()->type);
        $this->assertSame($student->id, AttendanceEvent::sole()->performed_by);
        $this->get($base)->assertOk()->assertSee('Visszaállítás a névsorba')->assertSee('Előzmények');
        $this->post($base.'/attendances/'.$attendance->id.'/restore', [
            'participant_search' => 'Minta', 'participant_picker_open' => '1',
        ])->assertRedirect($base.'?participant_search=Minta')->assertSessionHasNoErrors()->assertSessionHas('participant_picker_open', true);
        $this->assertNull($attendance->fresh()->withdrawn_at);
        $this->assertTrue($createdAt->equalTo($attendance->fresh()->created_at));
        $this->assertSame($this->admin->id, $attendance->fresh()->created_by);
        $response = $this->postJson($api)->assertOk()->assertJsonPath('data.id', $attendance->id)
            ->assertJsonPath('data.withdrawn_at', null)->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.events.1.type', 'restored')->assertJsonPath('data.events.1.performed_by', $this->admin->id)
            ->assertDontSee('INTERNAL_SAMPLE_NOTE')->assertDontSee($student->email)->assertDontSee($this->admin->email);
        $this->assertSame(['id', 'type', 'performed_by', 'performed_by_name', 'created_at', 'updated_at'], array_keys($response->json('data.events.1')));
        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseCount('participants', 1);
        $this->assertDatabaseCount('attendance_events', 2);
        $this->get($base)->assertOk()->assertSee('Résztvevők (1 fő)')->assertSee('Visszaállítva')->assertSee($this->admin->name);
        $this->deleteJson($this->url($training).'/'.$attendance->id)->assertConflict();
        $this->getJson($this->url($training))->assertJsonCount(2, 'data.0.events');
        $this->actingAs($student, 'web')->get('/edzesre-jottem?training_session_id='.$training->id)->assertOk()
            ->assertSee('Mégsem veszek részt')->assertDontSee('Előzmények')->assertDontSee($this->admin->email);
    }

    public function test_repeated_withdraw_restore_cycles_are_recorded_without_duplicate_events(): void
    {
        foreach ([TrainingSessionStatus::Scheduled, TrainingSessionStatus::Completed] as $status) {
            $training = $this->training($status);
            $attendance = $this->withdrawnAttendance($training);
            $student = $attendance->participant->user;
            $api = $this->url($training).'/'.$attendance->id.'/restore';
            $this->postJson($api)->assertOk();
            app(ManageAttendances::class)->withdraw(['training_session_id' => $training->id], $student, session()->driver());
            app(ManageAttendances::class)->withdraw(['training_session_id' => $training->id], $student, session()->driver());
            $this->postJson($api)->assertOk();
            $this->assertSame(['withdrawn', 'restored', 'withdrawn', 'restored'], $attendance->events()->get()->map(fn ($event) => $event->type->value)->all());
            $this->assertNull($attendance->fresh()->withdrawn_at);
        }
        $this->assertDatabaseCount('attendances', 2);
        $this->assertDatabaseCount('attendance_events', 8);
    }

    public function test_cancelled_sessions_and_archived_participants_cannot_be_restored(): void
    {
        $training = $this->training();
        $attendance = $this->withdrawnAttendance($training);
        app(ManageTrainingSessions::class)->cancel($training);
        $this->postJson($this->url($training).'/'.$attendance->id.'/restore')->assertConflict();
        $web = '/admin/training-sessions/'.$training->id;
        $this->post($web.'/attendances/'.$attendance->id.'/restore')->assertRedirect($web)->assertSessionHasErrors('attendance');
        $this->get($web)->assertOk()->assertDontSee('Visszaállítás a névsorba');
        $other = $this->training();
        $archived = $this->withdrawnAttendance($other);
        $archived->participant->delete();
        $this->postJson($this->url($other).'/'.$archived->id.'/restore')->assertConflict();
        $this->assertNotNull($attendance->fresh()->withdrawn_at);
        $this->assertNotNull($archived->fresh()->withdrawn_at);
        $this->assertDatabaseCount('attendance_events', 2);
    }

    public function test_restoration_enforces_parent_binding_actor_validation_and_admin_access(): void
    {
        $training = $this->training();
        $other = $this->training();
        $attendance = $this->withdrawnAttendance($training);
        $api = $this->url($training).'/'.$attendance->id.'/restore';
        $web = '/admin/training-sessions/'.$training->id.'/attendances/'.$attendance->id.'/restore';
        $this->postJson($this->url($other).'/'.$attendance->id.'/restore')->assertNotFound();
        $this->post('/admin/training-sessions/'.$other->id.'/attendances/'.$attendance->id.'/restore')->assertNotFound();
        $this->postJson($api, ['performed_by' => $attendance->participant->user_id])->assertUnprocessable()->assertJsonValidationErrors('performed_by');
        Auth::forgetGuards();
        Auth::guard('web')->logout();
        $this->postJson($api)->assertUnauthorized();
        $this->post($web)->assertRedirect(route('login'));
        foreach ([User::factory()->approved()->create(), User::factory()->admin()->unverified()->create(),
            User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])] as $actor) {
            Auth::forgetGuards();
            $this->actingAs($actor, 'web');
            $this->postJson($api)->assertForbidden();
            $response = $this->post($web);
            if (! $actor->hasVerifiedEmail()) {
                $response->assertRedirect(route('verification.notice'));
            } elseif (! $actor->isApproved()) {
                $response->assertRedirect(route('approval.pending'));
            } else {
                $response->assertForbidden();
            }
        }
        $this->assertNotNull($attendance->fresh()->withdrawn_at);
        $this->assertDatabaseCount('attendance_events', 1);
        $this->actingAs($this->admin, 'web');
        $this->app['env'] = 'local';
        $this->postJson($api)->assertStatus(419);
        $this->post($web)->assertStatus(419);
    }

    public function test_history_write_failure_rolls_back_withdrawal_and_restoration(): void
    {
        $training = $this->training();
        $attendance = $this->withdrawnAttendance($training);
        $student = $attendance->participant->user;
        $action = app(ManageAttendances::class);
        AttendanceEvent::creating(fn () => throw new \RuntimeException('Sample history write failure'));
        try {
            try {
                $action->restore($training, $attendance, $this->admin);
                $this->fail('Restoration must roll back.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Sample history write failure', $exception->getMessage());
            }
            $this->assertNotNull($attendance->fresh()->withdrawn_at);
            $this->assertDatabaseCount('attendance_events', 1);
        } finally {
            AttendanceEvent::flushEventListeners();
        }
        $action->restore($training, $attendance, $this->admin);
        AttendanceEvent::creating(fn () => throw new \RuntimeException('Sample history write failure'));
        try {
            try {
                $action->withdraw(['training_session_id' => $training->id], $student, session()->driver());
                $this->fail('Withdrawal must roll back.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('Sample history write failure', $exception->getMessage());
            }
            $this->assertNull($attendance->fresh()->withdrawn_at);
            $this->assertDatabaseCount('attendance_events', 2);
        } finally {
            AttendanceEvent::flushEventListeners();
        }
    }

    public function test_existing_withdrawal_is_backfilled_with_original_time_and_unknown_actor(): void
    {
        $training = $this->training();
        $withdrawn = $this->add($training, $this->participant());
        $active = $this->add($training, $this->participant('Aktív Minta'));
        $withdrawn->withdrawn_at = now()->subHour();
        $withdrawn->save();
        $original = $withdrawn->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_11_01_000002_create_attendance_events_table.php');
        $migration->down();
        $migration->up();
        $event = AttendanceEvent::sole();
        $this->assertSame($withdrawn->id, $event->attendance_id);
        $this->assertSame(AttendanceEventType::Withdrawn, $event->type);
        $this->assertNull($event->performed_by);
        $this->assertTrue($withdrawn->fresh()->withdrawn_at->equalTo($event->created_at));
        $this->assertSame($original, $withdrawn->fresh()->getAttributes());
        $this->assertNull($active->fresh()->withdrawn_at);
        $this->get('/admin/training-sessions/'.$training->id)->assertOk()->assertSee('Nem ismert');
        $this->postJson($this->url($training).'/'.$withdrawn->id.'/restore')->assertOk();
        try {
            DB::table('attendances')->where('id', $withdrawn->id)->delete();
            $this->fail('History foreign key must preserve attendance.');
        } catch (QueryException) {
            $this->assertDatabaseHas('attendances', ['id' => $withdrawn->id]);
        }
    }

    public function test_attendance_writes_remain_csrf_protected(): void
    {
        $training = $this->training();
        $participant = $this->participant();
        $attendance = $this->add($training, $participant);
        $web = '/admin/training-sessions/'.$training->id.'/attendances';
        $this->app['env'] = 'local';
        $this->post($web, ['participant_id' => $participant->id])->assertStatus(419);
        $this->delete($web.'/'.$attendance->id)->assertStatus(419);
        $this->postJson($this->url($training), ['participant_id' => $participant->id])->assertStatus(419);
        $this->deleteJson($this->url($training).'/'.$attendance->id)->assertStatus(419);
        $this->assertDatabaseCount('attendances', 1);
    }
}
