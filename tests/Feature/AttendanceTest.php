<?php

namespace Tests\Feature;

use App\Actions\ManageAttendances;
use App\Actions\ManageTrainingSessions;
use App\Enums\ApprovalStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\Attendance;
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
        $this->assertSame(['id', 'training_session_id', 'participant_id', 'participant_name', 'created_by', 'created_at', 'updated_at'],
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
