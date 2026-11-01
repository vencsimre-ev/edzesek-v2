<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TrainingSessionWebTest extends TestCase
{
    use DatabaseMigrations;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['title' => 'Minta webes edzés', 'location' => 'Minta mászóterem',
            'starts_at' => '2026-10-30T18:00', 'ends_at' => '2026-10-30T19:00',
            'status' => 'scheduled'], $overrides);
    }

    private function training(array $overrides = []): TrainingSession
    {
        return TrainingSession::factory()->create(array_replace(['created_by' => $this->admin->id,
            'starts_at' => '2026-10-30 09:00:00', 'ends_at' => '2026-10-30 10:00:00'], $overrides));
    }

    public function test_public_navigation_links_to_admin_training_list_and_empty_list_renders(): void
    {
        $this->get('/')->assertOk()->assertSee(route('admin.training-sessions.index'));
        $this->get('/admin/training-sessions')->assertOk()->assertSee('Edzések')->assertSee('Új edzés')
            ->assertSee('Nincs megjeleníthető edzés.');
        $this->get('/admin/training-sessions/create')->assertOk()->assertSee('Új edzés')->assertDontSee('budapesti idő szerint')->assertSee('Fless!Buda boulder')->assertDontSee('name="price_huf"', false);
    }

    public function test_create_form_defaults_to_the_next_thursday_even_across_summer_time(): void
    {
        foreach ([
            ['2026-10-28 12:00:00', '2026-10-29T18:00', '2026-10-29T20:00'],
            ['2026-10-29 12:00:00', '2026-10-29T18:00', '2026-10-29T20:00'],
            ['2026-10-29 18:00:00', '2026-11-05T18:00', '2026-11-05T20:00'],
            ['2026-07-01 12:00:00', '2026-07-02T18:00', '2026-07-02T20:00'],
        ] as [$now, $start, $end]) {
            $this->travelTo(CarbonImmutable::parse($now, 'UTC'));
            $this->get('/admin/training-sessions/create')->assertOk()
                ->assertSee('value="'.$start.'"', false)->assertSee('value="'.$end.'"', false)
                ->assertSee('value="Fless!Buda boulder"', false)
                ->assertSee('step="60"', false)->assertDontSee('name="price_huf"', false)
                ->assertDontSee('budapesti idő')->assertDontSee('0.000001');
        }
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_web_creation_converts_budapest_time_and_shows_hungarian_details(): void
    {
        $this->post('/admin/training-sessions', $this->payload())->assertRedirect();
        $training = TrainingSession::sole();
        $this->assertSame('2026-10-30 17:00:00.000000', $training->getRawOriginal('starts_at'));
        $this->assertSame($this->admin->id, $training->created_by);
        $this->assertSame(TrainingSessionStatus::Scheduled, $training->status);
        $this->get('/admin/training-sessions/'.$training->id)->assertOk()->assertSee('Minta webes edzés')
            ->assertSee('Tervezett')->assertSee('2026. 10. 30. 18:00')->assertDontSee('3 000 Ft')->assertDontSee('+01:00');
        $this->get('/admin/training-sessions')->assertOk()->assertSee('Tervezett')
            ->assertSee('Művelet')->assertSee('Elmaradás jelzése')->assertDontSee('>Elmarad</a>', false);
        $this->assertSame(TrainingSessionStatus::Scheduled, $training->fresh()->status);
    }

    public function test_summer_times_and_historical_completed_creation_use_existing_rules(): void
    {
        $this->post('/admin/training-sessions', $this->payload(['starts_at' => '2026-07-01T18:00',
            'ends_at' => '2026-07-01T19:00', 'status' => 'completed']))->assertRedirect()->assertSessionHasNoErrors();
        $training = TrainingSession::sole();
        $this->assertSame('2026-07-01 16:00:00.000000', $training->getRawOriginal('starts_at'));
        $this->assertSame(TrainingSessionStatus::Completed, $training->status);
        $this->from('/admin/training-sessions/create')->post('/admin/training-sessions', $this->payload(['status' => 'completed']))
            ->assertRedirect('/admin/training-sessions/create')->assertSessionHasErrors('status');
        $this->assertDatabaseCount('training_sessions', 1);
    }

    public function test_invalid_form_restores_original_local_input_and_rejects_missing_dst_hour(): void
    {
        $this->from('/admin/training-sessions/create')->post('/admin/training-sessions', $this->payload([
            'ends_at' => '2026-10-30T17:00',
        ]))->assertRedirect('/admin/training-sessions/create')->assertSessionHasErrors('ends_at');
        $this->assertSame('2026-10-30T18:00', session()->getOldInput('starts_at'));
        $this->get('/admin/training-sessions/create')->assertSee('value="2026-10-30T18:00"', false);
        foreach (['2026-03-29T02:30', '2026-02-30T18:00', 'invalid'] as $start) {
            $this->from('/admin/training-sessions/create')->post('/admin/training-sessions', $this->payload(['starts_at' => $start]))
                ->assertSessionHasErrors('starts_at');
        }
        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_local_filters_support_blank_all_status_stable_pagination_and_preserve_local_links(): void
    {
        $this->training(['starts_at' => '2026-10-30 08:00:00', 'ends_at' => '2026-10-30 09:00:00', 'title' => 'Alsó határ előtt']);
        $lower = $this->training(['title' => 'Alsó határon']);
        $higher = $this->training(['starts_at' => '2026-10-30 10:00:00', 'ends_at' => '2026-10-30 11:00:00', 'title' => 'Későbbi minta']);
        $this->training(['starts_at' => '2026-10-30 11:00:00', 'ends_at' => '2026-10-30 12:00:00', 'title' => 'Felső határon']);
        $this->training(['status' => TrainingSessionStatus::Cancelled, 'title' => 'Elmaradt minta']);
        $filters = ['status' => 'scheduled', 'starts_from' => '2026-10-30T10:00', 'starts_before' => '2026-10-30T12:00', 'per_page' => 1];
        $response = $this->get('/admin/training-sessions?'.http_build_query($filters))->assertOk()->assertSee('Alsó határon')->assertDontSee('Későbbi minta')->assertDontSee('Alsó határ előtt')
            ->assertDontSee('Felső határon')->assertDontSee('Elmaradt minta');
        $paginator = $response->viewData('sessions');
        $this->assertSame(2, $paginator->total());
        $this->assertSame($lower->id, $paginator->items()[0]->id);
        parse_str(parse_url($paginator->nextPageUrl(), PHP_URL_QUERY), $next);
        $this->assertSame('2026-10-30T10:00', $next['starts_from']);
        $this->assertSame('2026-10-30T12:00', $next['starts_before']);
        $this->get('/admin/training-sessions?'.http_build_query($next))->assertOk()->assertViewHas('sessions', fn ($sessions) => $sessions->items()[0]->id === $higher->id);
        $this->get('/admin/training-sessions?status=&starts_from=&starts_before=')->assertOk()
            ->assertViewHas('sessions', fn ($sessions) => $sessions->total() === 5);
        $this->from('/admin/training-sessions')->get('/admin/training-sessions?status=invalid')->assertSessionHasErrors('status');
    }

    public function test_edit_preserves_precision_and_cannot_change_protected_fields(): void
    {
        $training = $this->training(['starts_at' => '2026-10-30 09:00:00.123456']);
        $base = '/admin/training-sessions/'.$training->id;
        $this->get($base.'/edit')->assertOk()->assertSee('value="2026-10-30T10:00"', false)
            ->assertDontSee('name="status"', false)->assertDontSee('step="0.000001"', false);
        $this->patch($base, ['title' => 'Új mintacím', 'starts_at' => '2026-10-30T10:00',
            'ends_at' => '2026-10-30T11:00'])->assertRedirect($base)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-30 09:00:00.123456', $training->fresh()->getRawOriginal('starts_at'));
        foreach (['created_by' => 999, 'status' => 'completed', 'cancellation_reason' => 'Tiltott'] as $field => $value) {
            $this->from($base.'/edit')->patch($base, [$field => $value])->assertSessionHasErrors($field);
        }
        $this->assertSame($this->admin->id, $training->fresh()->created_by);
        $this->assertSame(TrainingSessionStatus::Scheduled, $training->fresh()->status);
    }

    public function test_unchanged_ambiguous_autumn_time_keeps_its_original_instant(): void
    {
        // 02:30 occurs twice; this record uses the earlier, summer-time occurrence.
        $training = $this->training(['starts_at' => '2026-10-25 00:30:00', 'ends_at' => '2026-10-25 03:00:00']);
        $this->patch('/admin/training-sessions/'.$training->id, ['title' => 'Megőrzött időpont',
            'starts_at' => '2026-10-25T02:30', 'ends_at' => '2026-10-25T04:00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-25 00:30:00.000000', $training->fresh()->getRawOriginal('starts_at'));
        $this->post('/admin/training-sessions', $this->payload(['starts_at' => '2026-10-25T02:30',
            'ends_at' => '2026-10-25T03:30']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2026-10-25 01:30:00.000000', TrainingSession::latest('id')->firstOrFail()->getRawOriginal('starts_at'));
    }

    public function test_web_completion_shares_api_rules_and_repeat_keeps_timestamps(): void
    {
        $training = $this->training();
        $base = '/admin/training-sessions/'.$training->id;
        $this->post($base.'/complete')->assertRedirect($base)->assertSessionHas('message', 'Az edzés megtartottként rögzítve.');
        $original = $training->fresh()->getAttributes();
        $this->travel(1)->hours();
        $this->post($base.'/complete')->assertRedirect($base)->assertSessionHasNoErrors();
        $this->assertSame($original, $training->fresh()->getAttributes());
        $this->post($base.'/cancel')->assertRedirect($base)->assertSessionHasErrors('training_session');
        $this->get($base)->assertOk()->assertSee('Megtartott')->assertDontSee('Megtartottként rögzítem')->assertDontSee('Szerkesztés');
        $future = $this->training(['ends_at' => now()->addDay()]);
        $futureBase = '/admin/training-sessions/'.$future->id;
        $this->from($futureBase)->post($futureBase.'/complete')->assertRedirect($futureBase)->assertSessionHasErrors('ends_at');
        $this->assertSame(TrainingSessionStatus::Scheduled, $future->fresh()->status);
    }

    public function test_cancellation_preserves_reason_and_closed_edit_produces_hungarian_error(): void
    {
        $training = $this->training();
        $base = '/admin/training-sessions/'.$training->id;
        $this->from($base)->post($base.'/cancel', ['cancellation_reason' => str_repeat('x', 2001)])->assertSessionHasErrors('cancellation_reason');
        $this->post($base.'/cancel', ['cancellation_reason' => '<script>Minta indok</script>'])->assertRedirect($base)->assertSessionHas('message', 'Az edzés elmaradtként rögzítve.');
        $original = $training->fresh()->getAttributes();
        $this->travel(1)->hours();
        $this->post($base.'/cancel', ['cancellation_reason' => 'Másik indok'])->assertRedirect($base)->assertSessionHasNoErrors();
        $this->assertSame($original, $training->fresh()->getAttributes());
        $this->post($base.'/complete')->assertRedirect($base)->assertSessionHasErrors('training_session');
        $this->patch($base, ['title' => 'Tiltott'])->assertRedirect($base)->assertSessionHasErrors('training_session');
        $this->get($base.'/edit')->assertRedirect($base)->assertSessionHasErrors('training_session');
        $this->get($base)->assertOk()->assertSee('Elmaradt')->assertSee('&lt;script&gt;Minta indok&lt;/script&gt;', false)
            ->assertDontSee('<script>Minta indok</script>', false)->assertDontSee('Megtartottként rögzítem');
    }

    public function test_web_physical_deletion_requires_confirmation_and_preserves_the_creator(): void
    {
        foreach (TrainingSessionStatus::cases() as $status) {
            $training = $this->training(['status' => $status]);
            $base = '/admin/training-sessions/'.$training->id;
            $this->get($base)->assertOk()->assertSee('Végleges törlés')->assertSee('name="confirm_delete"', false);
            foreach ([[], ['confirm_delete' => 0]] as $data) {
                $this->from($base)->delete($base, $data)->assertRedirect($base)->assertSessionHasErrors('confirm_delete');
                $this->assertDatabaseHas('training_sessions', ['id' => $training->id]);
            }
            $this->delete($base, ['confirm_delete' => 1])->assertRedirect(route('admin.training-sessions.index'))
                ->assertSessionHas('message', 'Az edzés véglegesen törölve.');
            $this->assertDatabaseMissing('training_sessions', ['id' => $training->id]);
            $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
            $this->get($base)->assertNotFound();
        }
    }

    public function test_all_web_routes_require_verified_approved_admin(): void
    {
        $training = $this->training();
        $base = '/admin/training-sessions';
        $operations = [['get', $base], ['get', $base.'/create'], ['post', $base], ['get', $base.'/'.$training->id],
            ['get', $base.'/'.$training->id.'/edit'], ['patch', $base.'/'.$training->id], ['delete', $base.'/'.$training->id],
            ['post', $base.'/'.$training->id.'/complete'], ['post', $base.'/'.$training->id.'/cancel']];
        Auth::forgetGuards();
        Auth::guard('web')->logout();
        foreach ($operations as [$method, $url]) {
            $this->{$method}($url, $this->payload())->assertRedirect(route('login'));
        }
        foreach ([User::factory()->approved()->create(), User::factory()->create(), User::factory()->unverified()->create(),
            User::factory()->admin()->unverified()->create(),
            User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])] as $actor) {
            Auth::forgetGuards();
            $this->actingAs($actor);
            foreach ($operations as [$method, $url]) {
                $response = $this->{$method}($url, $this->payload());
                if (! $actor->hasVerifiedEmail()) {
                    $response->assertRedirect(route('verification.notice'));
                } elseif (! $actor->isApproved()) {
                    $response->assertRedirect(route('approval.pending'));
                } else {
                    $response->assertForbidden();
                }
            }
        }
        $this->assertDatabaseCount('training_sessions', 1);
        $this->assertSame(TrainingSessionStatus::Scheduled, $training->fresh()->status);
    }

    public function test_missing_session_and_forms_require_csrf(): void
    {
        $this->get('/admin/training-sessions/99999')->assertNotFound();
        $training = $this->training();
        $base = '/admin/training-sessions/'.$training->id;
        $this->delete('/admin/training-sessions/99999', ['confirm_delete' => 1])->assertNotFound();
        $this->app['env'] = 'local';
        $this->post('/admin/training-sessions', $this->payload())->assertStatus(419);
        $this->patch($base, ['title' => 'Tiltott'])->assertStatus(419);
        $this->post($base.'/complete')->assertStatus(419);
        $this->post($base.'/cancel')->assertStatus(419);
        $this->delete($base, ['confirm_delete' => 1])->assertStatus(419);
        $this->assertSame(TrainingSessionStatus::Scheduled, $training->fresh()->status);
    }
}
