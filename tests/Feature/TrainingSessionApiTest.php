<?php

namespace Tests\Feature;

use App\Actions\ManageTrainingSessions;
use App\Enums\ApprovalStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class TrainingSessionApiTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create();
        $this->withHeader('Origin', 'http://127.0.0.1:8000');
        $this->actingAs($this->admin, 'web');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Minta mászóedzés', 'location' => 'Minta mászóterem',
            'starts_at' => '2026-10-30T18:00:00+01:00',
            'ends_at' => '2026-10-30T19:00:00+01:00', 'price_huf' => 3000,
        ], $overrides);
    }

    private function training(array $overrides = []): TrainingSession
    {
        return TrainingSession::factory()->create(array_replace([
            'created_by' => $this->admin->id,
            'starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 11:00:00',
        ], $overrides));
    }

    public function test_admin_creates_scheduled_session_with_explicit_resource_and_utc_storage(): void
    {
        $response = $this->postJson('/api/v1/training-sessions', $this->payload(['price_huf' => 0]))
            ->assertCreated()->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.starts_at', '2026-10-30T17:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-10-30T18:00:00.000000Z')
            ->assertJsonPath('data.price_huf', 0)->assertJsonPath('data.created_by', $this->admin->id)
            ->assertJsonPath('data.cancellation_reason', null);
        $session = TrainingSession::sole();
        $this->assertSame('2026-10-30 17:00:00.000000', $session->getRawOriginal('starts_at'));
        $this->assertSame(TrainingSessionStatus::Scheduled, $session->status);
        $this->assertTrue($session->creator->is($this->admin));
        $this->getJson('/api/v1/training-sessions/'.$session->id)->assertExactJson($response->json());
        $this->assertSame(['id', 'title', 'location', 'starts_at', 'ends_at', 'price_huf', 'status',
            'cancellation_reason', 'created_by', 'created_at', 'updated_at'], array_keys($response->json('data')));
        foreach (['user', 'creator', 'password', 'remember_token', 'google_id'] as $field) {
            $response->assertJsonMissingPath('data.'.$field);
        }
        $this->assertNotNull($session->created_at);
        $this->assertNotNull($session->updated_at);
        // No automatic overlap restriction.
        $this->postJson('/api/v1/training-sessions', $this->payload())->assertCreated();
    }

    public function test_historical_completed_and_past_scheduled_sessions_can_be_created(): void
    {
        $past = ['starts_at' => '2026-10-29T18:00:00+01:00', 'ends_at' => '2026-10-29T19:00:00+01:00'];
        $this->postJson('/api/v1/training-sessions', $this->payload($past + ['status' => 'completed']))
            ->assertCreated()->assertJsonPath('data.status', 'completed');
        $this->postJson('/api/v1/training-sessions', $this->payload($past))
            ->assertCreated()->assertJsonPath('data.status', 'scheduled');
    }

    public function test_initial_status_requires_scheduled_or_completed_with_strictly_past_end(): void
    {
        foreach (['cancelled', 'unknown', null] as $status) {
            $this->postJson('/api/v1/training-sessions', $this->payload(['status' => $status]))
                ->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        $this->postJson('/api/v1/training-sessions', $this->payload(['status' => 'completed']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson('/api/v1/training-sessions', $this->payload([
            'status' => 'completed', 'starts_at' => '2026-10-30T11:00:00Z', 'ends_at' => '2026-10-30T12:00:00Z',
        ]))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_invalid_dates_duration_price_and_required_fields_are_rejected(): void
    {
        foreach ([
            ['starts_at', '2026-10-30T19:00:00+01:00'],
            ['starts_at', '2026-10-30T20:00:00+01:00'],
            ['starts_at', '2026-10-30T18:00:00'],
            ['starts_at', '2026-02-30T18:00:00+01:00'],
            ['starts_at', '2026-10-30T25:00:00+01:00'],
            ['ends_at', 'tomorrow'], ['ends_at', '2026-10-30T19:00:00'],
            ['price_huf', -1], ['price_huf', 1.5], ['price_huf', null],
            ['title', ''], ['title', str_repeat('x', 256)],
            ['location', null], ['location', str_repeat('x', 256)],
        ] as [$field, $value]) {
            $this->postJson('/api/v1/training-sessions', $this->payload([$field => $value]))->assertUnprocessable();
        }
        $this->postJson('/api/v1/training-sessions', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'location', 'starts_at', 'ends_at', 'price_huf']);
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_offset_and_fractional_seconds_are_compared_as_utc_instants(): void
    {
        // Local clock values appear reversed, but UTC duration is positive.
        $this->postJson('/api/v1/training-sessions', $this->payload([
            'starts_at' => '2026-10-30T18:00:00.123456+02:00',
            'ends_at' => '2026-10-30T17:00:00.123457+01:00',
        ]))->assertCreated()->assertJsonPath('data.starts_at', '2026-10-30T16:00:00.123456Z')
            ->assertJsonPath('data.ends_at', '2026-10-30T16:00:00.123457Z');
        $this->postJson('/api/v1/training-sessions', $this->payload([
            'starts_at' => '2026-10-30T18:00:00+02:00', 'ends_at' => '2026-10-30T17:00:00+01:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_list_filters_time_bounds_inclusively_and_exclusively_with_stable_pagination(): void
    {
        $this->training(['starts_at' => '2026-10-29 09:00:00', 'ends_at' => '2026-10-29 10:00:00']);
        $lower = $this->training(['starts_at' => '2026-10-30 09:00:00', 'ends_at' => '2026-10-30 10:00:00']);
        $firstTie = $this->training(['starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 11:00:00']);
        $lastTie = $this->training(['starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 11:00:00']);
        $this->training(['starts_at' => '2026-10-30 11:00:00', 'ends_at' => '2026-10-30 12:00:00']);
        $cancelled = $this->training(['status' => TrainingSessionStatus::Cancelled]);
        $query = http_build_query(['status' => 'scheduled', 'starts_from' => '2026-10-30T10:00:00+01:00',
            'starts_before' => '2026-10-30T12:00:00+01:00', 'per_page' => 2]);
        $response = $this->getJson('/api/v1/training-sessions?'.$query)->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data');
        $this->assertSame([$lastTie->id, $firstTie->id], array_column($response->json('data'), 'id'));
        parse_str(parse_url($response->json('links.next'), PHP_URL_QUERY), $next);
        $this->assertSame('scheduled', $next['status']);
        $this->assertSame('2026-10-30T10:00:00+01:00', $next['starts_from']);
        $this->assertSame('2026-10-30T12:00:00+01:00', $next['starts_before']);
        $this->assertSame('2', $next['per_page']);
        $this->getJson('/api/v1/training-sessions?'.$query.'&page=2')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $lower->id);
        $this->getJson('/api/v1/training-sessions?status=cancelled')->assertJsonPath('data.0.id', $cancelled->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/training-sessions')->assertJsonPath('meta.total', 6)->assertJsonPath('meta.per_page', 20);
    }

    public function test_invalid_filters_and_pagination_return_json_validation_errors(): void
    {
        foreach (['status=other', 'per_page=0', 'per_page=101', 'page=0', 'starts_from=2026-10-30',
            'starts_before=2026-10-30T12:00:00', 'starts_from[]=invalid'] as $query) {
            $this->get('/api/v1/training-sessions?'.$query)->assertUnprocessable()
                ->assertHeader('Content-Type', 'application/json')->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_scheduled_session_supports_partial_updates_and_validates_against_saved_time_bounds(): void
    {
        $session = $this->training();
        $url = '/api/v1/training-sessions/'.$session->id;
        $this->patchJson($url, ['title' => 'Átnevezett edzés', 'location' => 'Másik mintaterem', 'price_huf' => 0])
            ->assertOk()->assertJsonPath('data.title', 'Átnevezett edzés')->assertJsonPath('data.price_huf', 0);
        $this->patchJson($url, ['starts_at' => '2026-10-30T11:30:00Z'])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->patchJson($url, ['ends_at' => '2026-10-30T09:00:00Z'])->assertUnprocessable();
        $this->patchJson($url, ['starts_at' => '2026-10-30T12:00:00+02:00'])->assertOk()
            ->assertJsonPath('data.starts_at', '2026-10-30T10:00:00.000000Z');
        $this->patchJson($url, ['ends_at' => '2026-10-30T12:30:00+01:00'])->assertOk()
            ->assertJsonPath('data.ends_at', '2026-10-30T11:30:00.000000Z');
        $this->patchJson($url, ['starts_at' => '2026-10-30T13:00:00Z', 'ends_at' => '2026-10-30T14:00:00Z'])->assertOk();
        $this->patchJson($url, ['starts_at' => '2026-10-30T16:00:00Z', 'ends_at' => '2026-10-30T15:00:00Z'])->assertUnprocessable();
        $this->patchJson($url, ['title' => null, 'price_huf' => -1])->assertUnprocessable();
        $this->assertSame('2026-10-30 13:00:00.000000', $session->fresh()->getRawOriginal('starts_at'));
    }

    public function test_protected_fields_are_rejected_even_when_null_and_creator_is_not_client_controlled(): void
    {
        $session = $this->training();
        foreach (['status' => 'completed', 'created_by' => 999, 'cancellation_reason' => 'Tiltott'] as $field => $value) {
            foreach ([$value, null, ''] as $input) {
                $this->patchJson('/api/v1/training-sessions/'.$session->id, [$field => $input])
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        foreach (['created_by' => $this->admin->id, 'cancellation_reason' => 'Indok'] as $field => $value) {
            $this->postJson('/api/v1/training-sessions', $this->payload([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(TrainingSessionStatus::Scheduled, $session->fresh()->status);
        $this->assertSame($this->admin->id, $session->fresh()->created_by);
        $this->assertNull($session->fresh()->cancellation_reason);
    }

    public function test_complete_at_exact_end_is_allowed_and_repeated_completion_is_a_no_op(): void
    {
        $session = $this->training(['ends_at' => now()]);
        $url = '/api/v1/training-sessions/'.$session->id.'/complete';
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'completed');
        $completedData = $session->fresh()->getAttributes();
        $this->travel(1)->hours();
        $this->postJson($url)->assertOk();
        $this->assertSame($completedData, $session->fresh()->getAttributes());
    }

    public function test_complete_before_end_is_rejected_and_cancelled_session_cannot_be_completed(): void
    {
        $future = $this->training(['ends_at' => now()->addMicrosecond()]);
        $this->postJson('/api/v1/training-sessions/'.$future->id.'/complete')->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $cancelled = $this->training(['status' => TrainingSessionStatus::Cancelled, 'cancellation_reason' => 'Minta indok']);
        $original = $cancelled->fresh()->getAttributes();
        $this->postJson('/api/v1/training-sessions/'.$cancelled->id.'/complete')->assertConflict();
        $this->assertSame($original, $cancelled->fresh()->getAttributes());
    }

    public function test_cancel_is_idempotent_preserves_first_reason_and_rejects_completed_session(): void
    {
        $session = $this->training();
        $url = '/api/v1/training-sessions/'.$session->id.'/cancel';
        $this->postJson($url, ['cancellation_reason' => 'Minta elmaradási indok'])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.cancellation_reason', 'Minta elmaradási indok');
        $cancelledData = $session->fresh()->getAttributes();
        $this->travel(1)->hours();
        $this->postJson($url, ['cancellation_reason' => 'Másik indok'])->assertOk();
        $this->assertSame($cancelledData, $session->fresh()->getAttributes());
        $completed = $this->training(['status' => TrainingSessionStatus::Completed]);
        $this->postJson('/api/v1/training-sessions/'.$completed->id.'/cancel')->assertConflict()->assertJsonStructure(['message']);
        $this->assertSame(TrainingSessionStatus::Completed, $completed->fresh()->status);
    }

    public function test_cancel_reason_is_optional_and_has_a_2000_character_limit(): void
    {
        $session = $this->training();
        $url = '/api/v1/training-sessions/'.$session->id.'/cancel';
        $this->postJson($url, ['cancellation_reason' => str_repeat('x', 2001)])->assertUnprocessable()->assertJsonValidationErrors('cancellation_reason');
        $this->postJson($url)->assertOk()->assertJsonPath('data.cancellation_reason', null);
    }

    public function test_closed_sessions_cannot_be_edited_or_reopened(): void
    {
        foreach ([TrainingSessionStatus::Completed, TrainingSessionStatus::Cancelled] as $status) {
            $session = $this->training(['status' => $status]);
            $original = $session->fresh()->getAttributes();
            $this->patchJson('/api/v1/training-sessions/'.$session->id, ['title' => 'Tiltott átírás'])->assertConflict();
            $this->patchJson('/api/v1/training-sessions/'.$session->id, ['status' => 'scheduled'])->assertUnprocessable();
            $this->assertSame($original, $session->fresh()->getAttributes());
        }
    }

    public function test_all_endpoints_require_verified_approved_admin_and_return_json_errors(): void
    {
        $session = $this->training();
        $url = '/api/v1/training-sessions';
        $operations = [['get', $url], ['post', $url], ['get', $url.'/'.$session->id],
            ['patch', $url.'/'.$session->id], ['post', $url.'/'.$session->id.'/complete'], ['post', $url.'/'.$session->id.'/cancel']];
        Auth::forgetGuards();
        $this->app['auth']->guard('web')->logout();
        foreach ($operations as [$method, $endpoint]) {
            $this->{$method}($endpoint, $this->payload())->assertUnauthorized()->assertHeader('Content-Type', 'application/json')->assertHeaderMissing('Location');
        }
        foreach ([User::factory()->approved()->create(), User::factory()->create(),
            User::factory()->admin()->unverified()->create(),
            User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])] as $actor) {
            Auth::forgetGuards();
            $this->actingAs($actor, 'web');
            foreach ($operations as [$method, $endpoint]) {
                $this->{$method}($endpoint, $this->payload())->assertForbidden()->assertHeader('Content-Type', 'application/json')->assertHeaderMissing('Location');
            }
        }
        $this->assertDatabaseCount('training_sessions', 1);
        $this->assertSame(TrainingSessionStatus::Scheduled, $session->fresh()->status);
    }

    public function test_missing_sessions_are_json_404_and_delete_is_not_exposed(): void
    {
        $url = '/api/v1/training-sessions/99999';
        $this->get($url)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->patchJson($url, ['title' => 'Minta'])->assertNotFound();
        $this->postJson($url.'/complete')->assertNotFound();
        $this->postJson($url.'/cancel')->assertNotFound();
        $session = $this->training();
        $this->delete('/api/v1/training-sessions/'.$session->id)->assertStatus(405)->assertHeader('Content-Type', 'application/json');
        $this->assertNotNull($session->fresh());
    }

    public function test_completion_rechecks_current_end_time_instead_of_a_stale_model(): void
    {
        $session = $this->training();
        $session->fresh()->update(['ends_at' => now()->addDay()]);
        try {
            app(ManageTrainingSessions::class)->complete($session);
            $this->fail('A stale model must not allow early completion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ends_at', $exception->errors());
        }
        $this->assertSame(TrainingSessionStatus::Scheduled, $session->fresh()->status);
    }

    public function test_competing_sqlite_cancel_prevents_a_stale_update(): void
    {
        $session = $this->training();
        $this->withCompetingWrite($session, ['status' => 'cancelled', 'cancellation_reason' => 'Másik kérés'], function () use ($session) {
            try {
                app(ManageTrainingSessions::class)->update($session, ['title' => 'Tiltott']);
                $this->fail('A concurrent cancellation must prevent editing.');
            } catch (ConflictHttpException) {
                $current = TrainingSession::findOrFail($session->id);
                $this->assertSame(TrainingSessionStatus::Cancelled, $current->status);
                $this->assertSame('Minta mászóedzés', $current->title);
                $this->assertSame('Másik kérés', $current->cancellation_reason);
            }
        });
    }

    public function test_competing_sqlite_cancel_wins_over_completion_without_overwriting_reason(): void
    {
        $session = $this->training();
        $this->withCompetingWrite($session, ['status' => 'cancelled', 'cancellation_reason' => 'Másik kérés'], function () use ($session) {
            try {
                app(ManageTrainingSessions::class)->complete($session);
                $this->fail('A competing final state must not be overwritten.');
            } catch (ConflictHttpException) {
                $this->assertSame('Másik kérés', TrainingSession::findOrFail($session->id)->cancellation_reason);
            }
        });
    }

    public function test_competing_sqlite_completion_prevents_cancellation(): void
    {
        $session = $this->training();
        $this->withCompetingWrite($session, ['status' => 'completed'], function () use ($session) {
            try {
                app(ManageTrainingSessions::class)->cancel($session, 'Tiltott indok');
                $this->fail('A competing completion must prevent cancellation.');
            } catch (ConflictHttpException) {
                $current = TrainingSession::findOrFail($session->id);
                $this->assertSame(TrainingSessionStatus::Completed, $current->status);
                $this->assertNull($current->cancellation_reason);
            }
        });
    }

    public function test_competing_sqlite_cancellation_preserves_the_first_reason(): void
    {
        $session = $this->training();
        $this->withCompetingWrite($session, ['status' => 'cancelled', 'cancellation_reason' => 'Első indok'], function () use ($session) {
            $current = app(ManageTrainingSessions::class)->cancel($session, 'Második indok');
            $this->assertSame(TrainingSessionStatus::Cancelled, $current->status);
            $this->assertSame('Első indok', $current->cancellation_reason);
        });
    }

    public function test_competing_sqlite_time_edit_cannot_produce_invalid_duration(): void
    {
        $session = $this->training();
        $this->withCompetingWrite($session, ['ends_at' => '2026-10-30 10:30:00.000000'], function () use ($session) {
            try {
                app(ManageTrainingSessions::class)->update($session, ['starts_at' => '2026-10-30T10:45:00Z']);
                $this->fail('Partial edits must validate against the current database time bound.');
            } catch (ValidationException) {
                $current = TrainingSession::findOrFail($session->id);
                $this->assertTrue($current->starts_at->lt($current->ends_at));
                $this->assertSame('2026-10-30 10:00:00.000000', $current->getRawOriginal('starts_at'));
            }
        });
    }

    /** Commit a second connection's write after BEGIN but before the action's first UPDATE. */
    private function withCompetingWrite(TrainingSession $session, array $competingData, callable $operation): void
    {
        // Copy only this test's in-memory sample database to a disposable SQLite file.
        $path = tempnam(sys_get_temp_dir(), 'training-session-test-');
        unlink($path);
        DB::statement('VACUUM INTO ?', [$path]);
        $original = config('database.default');
        $configuration = array_replace(config('database.connections.sqlite'), ['database' => $path, 'url' => null, 'busy_timeout' => 1000]);
        config(['database.connections.training_race' => $configuration, 'database.connections.training_competitor' => $configuration]);
        DB::setDefaultConnection('training_race');
        $injected = false;
        DB::connection()->beforeExecuting(function (string $query) use (&$injected, $session, $competingData) {
            if (! $injected && str_starts_with($query, 'update "training_sessions"')) {
                $injected = true;
                DB::connection('training_competitor')->transaction(fn (Connection $connection) => $connection->table('training_sessions')
                    ->where('id', $session->id)->where('status', 'scheduled')->update($competingData));
            }
        });
        try {
            $operation();
            $this->assertTrue($injected, 'The competing connection must actually write.');
        } finally {
            DB::purge('training_race');
            DB::purge('training_competitor');
            DB::setDefaultConnection($original);
            unlink($path);
        }
    }
}
