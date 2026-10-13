<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ParticipantTest extends TestCase
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

    public function test_admin_api_crud_preserves_auth_data_and_archives_without_deleting_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $created = $this->postJson('/api/v1/participants', ['name' => 'Minta Vendég', 'phone' => '+36 30 000 0000',
            'notes' => 'Belső minta', 'user_id' => 1, 'role' => 'admin', 'approval_status' => 'approved']);
        $created->assertCreated()->assertJsonPath('data.user_id', null)->assertJsonPath('data.notes', 'Belső minta');
        $id = $created->json('data.id');
        $user = User::factory()->create();
        Participant::find($id)->user()->associate($user)->save();
        $authData = $user->fresh()->getAttributes();
        $this->getJson("/api/v1/participants/{$id}")->assertOk()->assertJsonPath('data.name', 'Minta Vendég')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.user');
        $this->patchJson("/api/v1/participants/{$id}", ['name' => 'Új név', 'email' => 'contact@example.test',
            'user_id' => 1, 'role' => 'admin'])->assertOk()->assertJsonPath('data.user_id', $user->id);
        $this->assertSame($authData, $user->fresh()->getAttributes());
        $this->deleteJson("/api/v1/participants/{$id}")->assertNoContent();
        $this->assertSoftDeleted('participants', ['id' => $id]);
        $this->assertSame($authData, $user->fresh()->getAttributes());
        $this->getJson('/api/v1/participants')->assertJsonPath('meta.total', 0);
        foreach (['get', 'patch', 'delete'] as $method) {
            $this->{$method.'Json'}("/api/v1/participants/{$id}")->assertNotFound();
        }
        $this->postJson("/api/v1/participants/{$id}/link-user", ['user_id' => $user->id])->assertNotFound();
    }

    public function test_search_matches_each_contact_field_and_pagination_preserves_filters(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->guest(['name' => 'Needle név']);
        $this->guest(['email' => 'needle@example.test']);
        $this->guest(['phone' => 'needle-phone']);
        $this->guest(['name' => 'Needle archivált'])->delete();
        $this->guest(['name' => 'Másik']);
        $this->getJson('/api/v1/participants?search=NEEDLE&per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('links.next', fn ($url) => str_contains($url, 'search=NEEDLE') && str_contains($url, 'per_page=2'));
        $this->getJson('/api/v1/participants?search=NEEDLE&per_page=2&page=2')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/participants?search=%25')->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/participants?search[]=x')->assertUnprocessable();
        $this->getJson('/api/v1/participants?per_page=101&page=0')->assertUnprocessable();
        $this->postJson('/api/v1/participants', ['name' => '', 'email' => 'invalid', 'phone' => str_repeat('x', 51)])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    public function test_guests_receive_json_and_all_non_admin_states_are_denied_every_operation(): void
    {
        $participant = $this->guest();
        $student = User::factory()->create();
        $this->get('/api/v1/participants')->assertUnauthorized()->assertHeader('Content-Type', 'application/json');
        $this->get('/admin/participants')->assertRedirect(route('login'));
        foreach ([User::factory()->unverified()->create(), $student, User::factory()->approved()->create(),
            User::factory()->admin()->unverified()->create(), User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])] as $actor) {
            Auth::forgetGuards();
            $this->actingAs($actor);
            foreach ([['get', '/api/v1/participants'], ['post', '/api/v1/participants'],
                ['get', "/api/v1/participants/{$participant->id}"], ['patch', "/api/v1/participants/{$participant->id}"],
                ['delete', "/api/v1/participants/{$participant->id}"], ['post', "/api/v1/participants/{$participant->id}/link-user"]] as [$method, $url]) {
                $this->{$method.'Json'}($url, ['name' => 'Tiltott', 'user_id' => $student->id])->assertForbidden();
            }
            foreach ([['get', '/admin/participants'], ['get', '/admin/participants/create'], ['post', '/admin/participants'],
                ['get', "/admin/participants/{$participant->id}"], ['get', "/admin/participants/{$participant->id}/edit"],
                ['patch', "/admin/participants/{$participant->id}"], ['delete', "/admin/participants/{$participant->id}"],
                ['get', "/admin/participants/{$participant->id}/link-user"], ['post', "/admin/participants/{$participant->id}/link-user"]] as [$method, $url]) {
                $response = $this->{$method}($url, ['name' => 'Tiltott', 'user_id' => $student->id]);
                if (! $actor->hasVerifiedEmail() || ! $actor->isApproved()) {
                    $response->assertRedirect();
                } else {
                    $response->assertForbidden();
                }
            }
        }
        $this->assertSame('Minta Vendég', $participant->fresh()->name);
        $this->assertNull($participant->fresh()->user_id);
        $this->assertDatabaseCount('participants', 1);
    }

    public function test_student_me_never_exposes_notes_or_other_participants(): void
    {
        $student = User::factory()->approved()->create();
        $student->participant()->create(['name' => 'Saját', 'notes' => 'SECRET_INTERNAL_SAMPLE']);
        $this->guest(['name' => 'Másik résztvevő']);
        $this->actingAs($student)->getJson('/api/v1/me')->assertOk()->assertJsonMissingPath('data.notes')
            ->assertDontSee('SECRET_INTERNAL_SAMPLE')->assertDontSee('Másik résztvevő');
    }

    public function test_explicit_link_is_idempotent_and_does_not_change_contact_or_auth_fields(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $student = User::factory()->unverified()->create();
        $guest = $this->guest();
        $authData = $student->fresh()->getAttributes();
        for ($i = 0; $i < 2; $i++) {
            $this->postJson("/api/v1/participants/{$guest->id}/link-user", ['user_id' => $student->id,
                'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()])
                ->assertOk()->assertJsonPath('data.user_id', $student->id)->assertJsonPath('data.email', 'guest@example.test');
        }
        $this->assertDatabaseCount('participants', 1);
        $this->assertSame($authData, $student->fresh()->getAttributes());
    }

    public function test_link_fills_missing_email_and_repeated_link_preserves_later_contact_changes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $student = User::factory()->create();
        $guest = $this->guest(['email' => null]);
        $url = "/api/v1/participants/{$guest->id}/link-user";
        $authData = $student->fresh()->getAttributes();
        $this->postJson($url, ['user_id' => $student->id])->assertOk()
            ->assertJsonPath('data.email', $student->email);
        $guest->refresh()->update(['email' => 'contact@example.test']);
        $this->postJson($url, ['user_id' => $student->id])->assertOk()
            ->assertJsonPath('data.email', 'contact@example.test');
        $this->assertSame($authData, $student->fresh()->getAttributes());
    }

    public function test_occupied_account_including_archived_and_occupied_participant_produce_conflicts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $student = User::factory()->create();
        $linked = $student->participant()->create(['name' => 'Kapcsolt']);
        $guest = $this->guest();
        $url = "/api/v1/participants/{$guest->id}/link-user";
        $this->postJson($url, ['user_id' => $student->id])->assertConflict()->assertJsonStructure(['message']);
        $other = User::factory()->create();
        $this->postJson("/api/v1/participants/{$linked->id}/link-user", ['user_id' => $other->id])->assertConflict();
        $linked->delete();
        $this->postJson($url, ['user_id' => $student->id])->assertConflict();
        $this->postJson($url, ['user_id' => $admin->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->postJson($url, ['user_id' => 9999])->assertUnprocessable();
        $this->assertNull($guest->fresh()->user_id);
        $this->assertSame($student->id, $linked->fresh()->user_id);
        $this->assertDatabaseCount('participants', 2);
    }

    public function test_database_constraints_protect_link_and_user_deletion_preserves_history(): void
    {
        $student = User::factory()->create();
        $participant = $student->participant()->create(['name' => 'Történet']);
        try {
            $student->participant()->create(['name' => 'Második']);
            $this->fail('Duplicate connection must fail.');
        } catch (UniqueConstraintViolationException) {
            $this->assertDatabaseCount('participants', 1);
        }
        $student->delete();
        $this->assertSame('Történet', $participant->fresh()->name);
        $this->assertNull($participant->fresh()->user_id);
    }

    public function test_admin_web_crud_search_link_and_conflict_messages(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/participants/create')->assertOk()->assertSee('Új vendég');
        $this->post('/admin/participants', ['name' => 'Web Vendég', 'notes' => '<script>sample</script>'])->assertRedirect();
        $participant = Participant::sole();
        $base = "/admin/participants/{$participant->id}";
        $this->get('/admin/participants?search=Web')->assertOk()->assertSee('Web Vendég')
            ->assertSee(route('admin.participants.show', $participant))
            ->assertDontSee('<th>Telefon</th>', false)->assertDontSee('<th>Fiók</th>', false);
        $this->get($base)->assertOk()->assertSee('&lt;script&gt;sample&lt;/script&gt;', false)->assertDontSee('<script>sample</script>', false);
        $this->get($base.'/edit')->assertOk();
        $this->patch($base, ['name' => 'Web Mentett'])->assertRedirect(route('admin.participants.show', $participant));
        $student = User::factory()->create();
        $student->participant()->create(['name' => 'Foglalt']);
        $this->get($base.'/link-user')->assertOk()->assertSee($student->email);
        $this->from($base.'/link-user')->post($base.'/link-user', ['user_id' => $student->id])
            ->assertRedirect($base.'/link-user')->assertSessionHasErrors('user_id');
        $available = User::factory()->create();
        $this->post($base.'/link-user', ['user_id' => $available->id])->assertRedirect(route('admin.participants.show', $participant));
        $this->assertSame($available->id, $participant->fresh()->user_id);
        $this->get('/admin/participants')->assertSee('Kapcsolt bejelentkezési fiók');
        $this->get($base)->assertSee('Törlés')->assertDontSee('Archiválás');
        $this->delete($base)->assertRedirect(route('admin.participants.index'));
        $this->get('/admin/participants')->assertDontSee('Web Mentett');
        $this->assertNotNull($available->fresh());
    }
}
