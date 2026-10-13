<?php

namespace Tests\Feature;

use App\Actions\AuthenticateWithGoogle;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\UserAwaitingApproval;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://127.0.0.1:8000/auth/google/callback']);
    }

    private function fakeGoogle(string $id = 'google-123', string $email = 'google@example.test', bool $verified = true): void
    {
        Socialite::fake('google', GoogleUser::fake([
            'id' => $id, 'name' => 'Minta Google Tanuló', 'email' => $email, 'email_verified' => $verified,
            'role' => 'admin', 'approval_status' => 'approved',
        ]));
    }

    public function test_google_registration_creates_verified_pending_student_and_notifies_admin_once(): void
    {
        $admin = User::factory()->admin()->create();
        $this->fakeGoogle();
        $this->withSession(['google.intent' => 'login'])->get('/auth/google/callback')->assertRedirect(route('home'));
        $student = User::where('google_id', 'google-123')->sole();
        $this->assertSame($student->name, $student->participant->name);
        $this->assertSame($student->email, $student->participant->email);
        $this->assertSame(Role::Student, $student->role);
        $this->assertSame(ApprovalStatus::Pending, $student->approval_status);
        $this->assertTrue($student->hasVerifiedEmail());
        $this->assertNull($student->approved_at);
        $this->assertNull($student->approved_by);
        $this->assertNotNull($student->created_at);
        $this->assertNotNull($student->updated_at);
        $this->assertAuthenticatedAs($student);
        $this->assertArrayNotHasKey('google_id', $student->toArray());
        $this->assertStringNotContainsString('fake-token', json_encode($student->getAttributes()));
        $this->assertStringNotContainsString('fake-refresh-token', json_encode($student->getAttributes()));
        Notification::assertSentToTimes($admin, UserAwaitingApproval::class, 1);
        $this->get('/home')->assertRedirect(route('approval.pending'));
        $this->post('/logout');
        Auth::forgetGuards();
        $this->withSession(['google.intent' => 'login'])->get('/auth/google/callback')->assertRedirect(route('home'));
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('participants', 1);
        Notification::assertSentToTimes($admin, UserAwaitingApproval::class, 1);
    }

    public function test_linked_approved_student_can_sign_in_and_access_application(): void
    {
        $user = User::factory()->approved()->create(['google_id' => 'google-123']);
        $this->fakeGoogle();
        $this->withSession(['google.intent' => 'login'])->get('/auth/google/callback')->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->get('/home')->assertOk();
        $this->assertSame($user->email, $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_email_collision_never_logs_in_or_auto_links_existing_account(): void
    {
        $user = User::factory()->admin()->create(['email' => 'google@example.test']);
        $this->fakeGoogle();
        $this->withSession(['google.intent' => 'login'])->get('/auth/google/callback')
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
        Notification::assertNothingSent();
    }

    public function test_verified_pending_user_can_explicitly_link_google_without_changing_approval_or_password(): void
    {
        $user = User::factory()->create(['email' => 'google@example.test']);
        $this->fakeGoogle();
        $this->actingAs($user)->post('/auth/google/link')->assertRedirect();
        $this->get('/auth/google/callback')->assertRedirect(route('home'));
        $user->refresh();
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->get('/home')->assertRedirect(route('approval.pending'));
        Notification::assertNothingSent();
    }

    public function test_linking_requires_login_verified_email_and_csrf(): void
    {
        $this->post('/auth/google/link')->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->post('/auth/google/link')->assertRedirect(route('verification.notice'));
        $user->email_verified_at = now();
        $user->save();
        $this->app['env'] = 'local';
        $this->post('/auth/google/link')->assertStatus(419);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_linking_rejects_different_email_and_google_identity_already_owned_by_another_user(): void
    {
        $user = User::factory()->create(['email' => 'local@example.test']);
        $this->fakeGoogle();
        $this->actingAs($user)->withSession(['google.intent' => $user->id])->get('/auth/google/callback')
            ->assertSessionHasErrors('google');
        $this->assertNull($user->fresh()->google_id);
        $user->email = 'google@example.test';
        $user->save();
        User::factory()->create(['google_id' => 'google-123']);
        $this->withSession(['google.intent' => $user->id])->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_existing_google_connection_cannot_be_replaced(): void
    {
        $user = User::factory()->create(['email' => 'google@example.test', 'google_id' => 'original-google']);
        $this->fakeGoogle();
        $this->actingAs($user)->withSession(['google.intent' => $user->id])->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertSame('original-google', $user->fresh()->google_id);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeGoogle(verified: false);
        $this->withSession(['google.intent' => 'login'])->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_callback_requires_matching_session_intent_and_does_not_switch_logged_in_account(): void
    {
        $this->fakeGoogle();
        $this->get('/auth/google/callback')->assertSessionHasErrors('google');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->withSession(['google.intent' => $other->id])->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->withSession(['google.intent' => 'login'])->get('/auth/google/callback')->assertSessionHasErrors('google');
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_real_socialite_redirect_sets_state_and_wrong_state_is_rejected_without_provider_request(): void
    {
        $response = $this->get('/auth/google/redirect')->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame(session('state'), $query['state']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame('test-client', $query['client_id']);
        $this->assertSame('http://127.0.0.1:8000/auth/google/callback', $query['redirect_uri']);
        Socialite::forgetDrivers();
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get('/auth/google/callback?state=wrong&code=sample-code')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_notification_is_discarded_when_google_creation_transaction_rolls_back(): void
    {
        User::factory()->admin()->create();
        $google = GoogleUser::fake(['email_verified' => true]);
        DB::beginTransaction();
        app(AuthenticateWithGoogle::class)->authenticate($google);
        Notification::assertNothingSent();
        DB::rollBack();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_button_is_available_only_when_configured(): void
    {
        $this->get('/login')->assertSee('Folytatás Google-fiókkal');
        $this->get('/register')->assertSee('Folytatás Google-fiókkal');
        config(['services.google.client_id' => null]);
        $this->get('/login')->assertDontSee('Folytatás Google-fiókkal');
        $this->get('/auth/google/redirect')->assertStatus(503);
    }
}
