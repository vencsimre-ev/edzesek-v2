<?php

namespace Tests\Feature;

use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WeeklyTrainingSessionsTest extends TestCase
{
    use DatabaseMigrations;

    private const URL = '/api/v1/training-sessions/sync-weekly';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['training.cron_key' => 'test-cron-key']);
        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'UTC'));
        $this->admin = User::factory()->admin()->create();
    }

    private function syncWeekly(): TestResponse
    {
        return $this->withToken('test-cron-key')->postJson(self::URL);
    }

    public function test_cron_route_requires_its_key_even_when_logged_in_as_admin(): void
    {
        $this->postJson(self::URL)->assertUnauthorized()->assertJsonStructure(['message']);
        $this->withToken('wrong-key')->postJson(self::URL)->assertUnauthorized();
        $this->actingAs($this->admin)->withoutHeader('Authorization')->postJson(self::URL)->assertUnauthorized();
        $this->assertDatabaseCount('training_sessions', 0);
        $this->getJson(self::URL)->assertNotFound();
    }

    public function test_missing_configuration_is_disabled_and_no_eligible_admin_is_a_json_error(): void
    {
        config(['training.cron_key' => null]);
        $this->syncWeekly()->assertStatus(503);
        config(['training.cron_key' => 'test-cron-key']);
        $this->admin->email_verified_at = null;
        $this->admin->save();
        User::factory()->approved()->create();
        $this->syncWeekly()->assertUnprocessable()->assertJsonStructure(['message']);
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_first_call_creates_eight_thursdays_and_repeat_creates_nothing(): void
    {
        $this->syncWeekly()->assertOk()->assertExactJson(['data' => ['created' => 8, 'completed' => 0]]);
        foreach (TrainingSession::orderBy('starts_at')->get() as $index => $session) {
            $start = $session->starts_at->setTimezone(config('training.timezone'));
            $this->assertSame('2026-11-05', $start->subWeeks($index)->toDateString());
            $this->assertSame('18:00', $start->format('H:i'));
            $this->assertSame('20:00', $session->ends_at->setTimezone(config('training.timezone'))->format('H:i'));
            $this->assertSame('Fless!Buda boulder', $session->location);
            $this->assertSame($this->admin->id, $session->created_by);
            $this->assertSame(TrainingSessionStatus::Scheduled, $session->status);
        }
        $original = TrainingSession::orderBy('id')->get()->toArray();
        $this->travel(1)->hours();
        $this->syncWeekly()->assertExactJson(['data' => ['created' => 0, 'completed' => 0]]);
        $this->assertSame($original, TrainingSession::orderBy('id')->get()->toArray());
    }

    public function test_existing_modified_and_cancelled_sessions_are_not_overwritten(): void
    {
        $manual = TrainingSession::factory()->create(['created_by' => $this->admin->id,
            'starts_at' => '2026-11-05 18:00:00', 'ends_at' => '2026-11-05 20:00:00',
            'title' => 'Módosított minta', 'location' => 'Másik terem']);
        $cancelled = TrainingSession::factory()->create(['created_by' => $this->admin->id,
            'starts_at' => '2026-11-12 17:00:00', 'ends_at' => '2026-11-12 19:00:00',
            'status' => TrainingSessionStatus::Cancelled, 'cancellation_reason' => 'Minta elmaradás']);
        $originalManual = $manual->fresh()->getAttributes();
        $originalCancelled = $cancelled->fresh()->getAttributes();
        $this->syncWeekly()->assertJsonPath('data.created', 6);
        $this->assertSame($originalManual, $manual->fresh()->getAttributes());
        $this->assertSame($originalCancelled, $cancelled->fresh()->getAttributes());
        $this->syncWeekly()->assertJsonPath('data.created', 0);
        $this->assertDatabaseCount('training_sessions', 8);
    }

    public function test_daily_rolling_window_adds_the_new_week_and_completes_elapsed_session(): void
    {
        $this->syncWeekly()->assertOk();
        $first = TrainingSession::orderBy('starts_at')->firstOrFail();
        $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00', 'UTC'));
        $this->syncWeekly()->assertExactJson(['data' => ['created' => 1, 'completed' => 1]]);
        $this->assertSame(TrainingSessionStatus::Completed, $first->fresh()->status);
        $this->assertDatabaseCount('training_sessions', 9);
    }

    public function test_elapsed_sessions_close_using_the_actual_end_and_cancelled_state_is_preserved(): void
    {
        $due = TrainingSession::factory()->create(['created_by' => $this->admin->id,
            'starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 12:00:00']);
        $later = TrainingSession::factory()->create(['created_by' => $this->admin->id,
            'starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 13:00:00']);
        $cancelled = TrainingSession::factory()->create(['created_by' => $this->admin->id,
            'starts_at' => '2026-10-29 17:00:00', 'ends_at' => '2026-10-29 19:00:00',
            'status' => TrainingSessionStatus::Cancelled, 'cancellation_reason' => 'Megőrzött indok']);
        $this->syncWeekly()->assertJsonPath('data.completed', 1);
        $this->assertSame(TrainingSessionStatus::Completed, $due->fresh()->status);
        $this->assertSame(TrainingSessionStatus::Scheduled, $later->fresh()->status);
        $this->assertSame(TrainingSessionStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame('Megőrzött indok', $cancelled->fresh()->cancellation_reason);
        $original = $due->fresh()->getAttributes();
        $this->travel(1)->minutes();
        $this->syncWeekly()->assertJsonPath('data.completed', 0);
        $this->assertSame($original, $due->fresh()->getAttributes());
    }

    public function test_fixed_local_time_survives_clock_change(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-18 12:00', 'UTC'));
        $this->syncWeekly()->assertJsonPath('data.created', 8);
        foreach (TrainingSession::all() as $session) {
            $this->assertSame('18:00', $session->starts_at->setTimezone(config('training.timezone'))->format('H:i'));
            $this->assertSame('20:00', $session->ends_at->setTimezone(config('training.timezone'))->format('H:i'));
        }
        $this->assertDatabaseHas('training_sessions', ['starts_at' => '2026-03-19 17:00:00.000000']);
        $this->assertDatabaseHas('training_sessions', ['starts_at' => '2026-04-02 16:00:00.000000']);
    }

    public function test_missing_thursday_after_physical_deletion_is_created_again(): void
    {
        $this->syncWeekly()->assertOk();
        $first = TrainingSession::orderBy('starts_at')->firstOrFail();
        $originalStart = $first->getRawOriginal('starts_at');
        $this->actingAs($this->admin)->deleteJson('/api/v1/training-sessions/'.$first->id)->assertNoContent();
        $this->assertDatabaseMissing('training_sessions', ['id' => $first->id]);
        $this->syncWeekly()->assertJsonPath('data.created', 1);
        $this->assertDatabaseCount('training_sessions', 8);
        $this->assertDatabaseHas('training_sessions', ['starts_at' => $originalStart]);
    }

    public function test_cleanup_migration_removes_only_retired_tracking_and_preserves_trainings(): void
    {
        $this->syncWeekly()->assertOk();
        $original = TrainingSession::orderBy('id')->get()->toArray();
        $migration = require database_path('migrations/2026_10_31_000000_drop_weekly_training_occurrences_table.php');
        $migration->down();
        DB::table('weekly_training_occurrences')->insert(['scheduled_on' => '2026-11-05',
            'training_session_id' => TrainingSession::firstOrFail()->id, 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $this->assertFalse(Schema::hasTable('weekly_training_occurrences'));
        $this->assertSame($original, TrainingSession::orderBy('id')->get()->toArray());
    }
}
