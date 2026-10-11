<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Models\User;
use App\Notifications\AccountApproved;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->withHeader('Origin', 'http://127.0.0.1:8000');
    }

    public function test_guests_receive_json_without_accept_header_or_redirect(): void
    {
        foreach (['/api/v1/me', '/api/v1/admin/users'] as $url) {
            $this->get($url)->assertUnauthorized()->assertHeader('Content-Type', 'application/json')
                ->assertJsonStructure(['message'])->assertHeaderMissing('Location');
        }
        $this->patch('/api/v1/admin/users/1/approve')->assertUnauthorized();
        $this->get('/api/v1/unknown')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }

    public function test_me_is_available_for_all_authenticated_states_and_has_explicit_fields(): void
    {
        foreach ([User::factory()->unverified()->create(), User::factory()->create(), User::factory()->approved()->create()] as $user) {
            Auth::forgetGuards();
            $this->actingAs($user, 'web')->get('/api/v1/me')->assertOk()->assertExactJson(['data' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'role' => 'student', 'approval_status' => $user->approval_status->value,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'approved_at' => $user->approved_at?->toISOString(), 'approved_by' => null,
                'created_at' => $user->created_at->toISOString(), 'updated_at' => $user->updated_at->toISOString(),
            ]]);
        }
    }

    public function test_real_login_session_cookie_authenticates_stateful_api(): void
    {
        $user = User::factory()->unverified()->create();
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $id = session()->getId();
        Auth::forgetGuards();
        $this->withCookie(config('session.cookie'), $id)->get('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_bearer_tokens_are_not_supported(): void
    {
        $this->withHeader('Authorization', 'Bearer 1|sample-personal-token')->get('/api/v1/me')->assertUnauthorized();
    }

    public function test_admin_endpoints_require_verified_approved_admin(): void
    {
        $target = User::factory()->create();
        $users = [User::factory()->unverified()->create(), User::factory()->create(),
            User::factory()->approved()->create(), User::factory()->admin()->unverified()->create(),
            User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending])];
        foreach ($users as $user) {
            Auth::forgetGuards();
            $this->actingAs($user, 'web')->get('/api/v1/admin/users')->assertForbidden()->assertHeaderMissing('Location');
            $this->patch('/api/v1/admin/users/'.$target->id.'/approve')->assertForbidden()->assertJsonStructure(['message']);
        }
        $this->assertSame(ApprovalStatus::Pending, $target->fresh()->approval_status);
        Notification::assertNothingSent();
    }

    public function test_admin_list_filters_verified_pending_users_and_paginates_resources(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();
        User::factory()->unverified()->create();
        $approved = User::factory()->approved()->create();
        $response = $this->actingAs($admin, 'web')->get('/api/v1/admin/users?approval_status=pending&per_page=2');
        $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)->assertJsonStructure(['data' => [['id', 'role', 'approval_status']], 'links', 'meta']);
        $this->assertStringContainsString('approval_status=pending', $response->json('links.next'));
        $this->assertStringContainsString('per_page=2', $response->json('links.next'));
        foreach ($response->json('data') as $user) {
            $this->assertSame('pending', $user['approval_status']);
            $this->assertNotNull($user['email_verified_at']);
            $this->assertSame(['id', 'name', 'email', 'role', 'approval_status', 'email_verified_at', 'approved_at', 'approved_by', 'created_at', 'updated_at'], array_keys($user));
        }
        $this->get('/api/v1/admin/users?approval_status=pending&per_page=2&page=2')->assertJsonCount(1, 'data');
        $this->get('/api/v1/admin/users?approval_status=approved')->assertJsonPath('meta.total', 2);
    }

    public function test_filter_and_pagination_validation_returns_json_422(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'web');
        foreach (['approval_status=other', 'per_page=101', 'per_page=0', 'page=0', 'page=abc'] as $query) {
            $this->get('/api/v1/admin/users?'.$query)->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_api_and_web_approval_share_rules_and_do_not_send_duplicate_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->actingAs($admin, 'web')->patch('/api/v1/admin/users/'.$student->id.'/approve', ['role' => 'admin', 'approved_by' => 999])
            ->assertOk()->assertJsonPath('data.approval_status', 'approved')->assertJsonPath('data.role', 'student')
            ->assertJsonPath('data.approved_by', $admin->id);
        $originalUpdatedAt = $student->fresh()->updated_at;
        $this->travel(1)->minutes();
        $this->patch('/admin/approvals/'.$student->id)->assertRedirect();
        $this->patch('/api/v1/admin/users/'.$student->id.'/approve')->assertOk();
        $this->assertTrue($originalUpdatedAt->equalTo($student->fresh()->updated_at));
        Notification::assertSentToTimes($student, AccountApproved::class, 1);
        $other = User::factory()->create();
        $this->patch('/admin/approvals/'.$other->id)->assertRedirect();
        $this->patch('/api/v1/admin/users/'.$other->id.'/approve')->assertOk()->assertJsonPath('data.approved_by', $admin->id);
        Notification::assertSentToTimes($other, AccountApproved::class, 1);
    }

    public function test_invalid_approval_and_missing_user_have_json_errors(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs(User::factory()->admin()->create(), 'web')->patch('/api/v1/admin/users/'.$user->id.'/approve')
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->patch('/api/v1/admin/users/99999/approve')->assertNotFound()->assertHeader('Content-Type', 'application/json');
        Notification::assertNothingSent();
    }

    public function test_stateful_api_requires_csrf_for_writes_with_real_session_cookie(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $id = session()->getId();
        $csrf = csrf_token();
        Auth::forgetGuards();
        $this->app['env'] = 'local';
        $this->withCookie(config('session.cookie'), $id)->patch('/api/v1/admin/users/'.$student->id.'/approve')
            ->assertStatus(419)->assertHeader('Content-Type', 'application/json');
        $this->assertSame(ApprovalStatus::Pending, $student->fresh()->approval_status);
        Auth::forgetGuards();
        $this->withHeader('X-CSRF-TOKEN', $csrf)->patch('/api/v1/admin/users/'.$student->id.'/approve')
            ->assertOk()->assertJsonPath('data.approval_status', 'approved');
    }

    public function test_resource_excludes_sensitive_provider_and_authentication_fields(): void
    {
        $user = User::factory()->create();
        $user->setAttribute('google_id', 'private-google-subject');
        $user->setAttribute('oauth_access_token', 'sample-sensitive-access-token');
        $user->setAttribute('oauth_refresh_token', 'sample-sensitive-refresh-token');
        $response = $this->actingAs($user, 'web')->get('/api/v1/me')->assertOk();
        foreach (['password', 'remember_token', 'google_id', 'oauth_access_token', 'oauth_refresh_token'] as $field) {
            $response->assertJsonMissingPath('data.'.$field);
        }
        $response->assertDontSee('sample-sensitive-access-token')->assertDontSee('sample-sensitive-refresh-token')
            ->assertDontSee('private-google-subject');
    }
}
