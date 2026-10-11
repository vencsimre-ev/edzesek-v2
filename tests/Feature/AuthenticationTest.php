<?php

namespace Tests\Feature;

use App\Actions\ApproveUser;
use App\Enums\ApprovalStatus;
use App\Enums\Role;
use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\UserAwaitingApproval;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function verificationUrl(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }

    public function test_registration_cannot_set_privileged_attributes(): void
    {
        $this->post('/register', [
            'name' => 'Minta Tanuló', 'email' => 'student@example.test',
            'password' => 'test-password', 'password_confirmation' => 'test-password',
            'role' => 'admin', 'approval_status' => 'approved',
            'email_verified_at' => now(), 'approved_at' => now(), 'approved_by' => 1,
        ])->assertRedirect('/home');

        $user = User::sole();
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
        $this->assertTrue($user->created_at->equalTo($user->updated_at));
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->approved_at);
        $this->assertNull($user->approved_by);
        $this->assertTrue(Hash::check('test-password', $user->password));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_validation_is_hungarian_and_does_not_store_password_confirmation(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'Minta', 'email' => 'invalid', 'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors([
            'email' => 'A(z) emailcím érvényes emailcím legyen.',
            'password',
        ]);
        $this->withCookie(config('session.cookie'), session()->getId())->get('/register')->assertSee('érvényes emailcím')->assertDontSee('value="short"', false);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_guests_cannot_access_protected_routes(): void
    {
        $this->get('/home')->assertRedirect('/login');
        $this->get('/admin/approvals')->assertRedirect('/login');
        $this->get('/approval-pending')->assertRedirect('/login');
    }

    public function test_unverified_users_can_only_use_verification_and_logout_auth_flows(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/home')->assertRedirect(route('verification.notice'));
        $this->get('/approval-pending')->assertRedirect(route('verification.notice'));
        $this->get('/admin/approvals')->assertRedirect(route('verification.notice'));
        $this->patch('/admin/approvals/'.$user->id)->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk()->assertSee('Erősítsd meg');
        $this->post('/email/verification-notification')->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/user/confirm-password')->assertRedirect(route('verification.notice'));
        $this->post('/user/confirm-password', ['password' => 'password'])->assertRedirect(route('verification.notice'));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_verified_pending_user_cannot_access_application_or_admin_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/home')->assertRedirect(route('approval.pending'));
        $this->get('/approval-pending')->assertOk()->assertSee('admin jóváhagyásra vár');
        $this->get('/admin/approvals')->assertRedirect(route('approval.pending'));
        $this->patch('/admin/approvals/'.$user->id)->assertRedirect(route('approval.pending'));
        $this->get('/user/confirm-password')->assertRedirect(route('approval.pending'));
        $this->assertSame(ApprovalStatus::Pending, $user->fresh()->approval_status);
    }

    public function test_verified_approved_student_can_access_home_but_not_admin_routes(): void
    {
        $user = User::factory()->approved()->create();
        $pending = User::factory()->create();
        $this->actingAs($user)->get('/home')->assertOk()->assertSee($user->name);
        $this->get('/approval-pending')->assertRedirect(route('home'));
        $this->get('/admin/approvals')->assertForbidden();
        $this->patch('/admin/approvals/'.$pending->id)->assertForbidden();
        $this->assertSame(ApprovalStatus::Pending, $pending->fresh()->approval_status);
        Notification::assertNothingSent();
    }

    public function test_approved_but_unverified_user_is_still_restricted(): void
    {
        $user = User::factory()->approved()->unverified()->create();
        $this->actingAs($user)->get('/home')->assertRedirect(route('verification.notice'));
    }

    public function test_pending_or_unverified_admin_is_restricted(): void
    {
        $user = User::factory()->admin()->create(['approval_status' => ApprovalStatus::Pending]);
        $this->actingAs($user)->get('/admin/approvals')->assertRedirect(route('approval.pending'));
        $user->approval_status = ApprovalStatus::Approved;
        $user->email_verified_at = null;
        $user->save();
        $this->get('/admin/approvals')->assertRedirect(route('verification.notice'));
    }

    public function test_valid_verification_notifies_admin_once_and_keeps_user_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $inactiveAdmin = User::factory()->admin()->unverified()->create();
        $student = User::factory()->unverified()->create();
        $url = $this->verificationUrl($student);
        $this->actingAs($student)->get($url)->assertRedirect('/home?verified=1');
        $this->assertTrue($student->fresh()->hasVerifiedEmail());
        $this->assertSame(ApprovalStatus::Pending, $student->fresh()->approval_status);
        Notification::assertSentTo($admin, UserAwaitingApproval::class,
            fn ($notification) => $notification->student->is($student));
        Notification::assertNotSentTo($inactiveAdmin, UserAwaitingApproval::class);
        $this->get($url)->assertRedirect();
        Notification::assertSentToTimes($admin, UserAwaitingApproval::class, 1);
        $this->get('/home')->assertRedirect(route('approval.pending'));
    }

    public function test_invalid_expired_or_wrong_user_verification_links_are_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->actingAs($user)->get($this->verificationUrl($user).'broken')->assertForbidden();
        $this->get($this->verificationUrl($user, -1))->assertForbidden();
        $this->get($this->verificationUrl($other))->assertForbidden();
        $wrongHash = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => 'wrong']);
        $this->get($wrongHash)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }

    public function test_admin_list_only_contains_verified_pending_users(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->create();
        $unverified = User::factory()->unverified()->create();
        $approved = User::factory()->approved()->create();
        $this->actingAs($admin)->get('/admin/approvals')->assertOk()
            ->assertSee($pending->email)->assertDontSee($unverified->email)->assertDontSee($approved->email);
    }

    public function test_approval_records_actor_and_time_and_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $createdAt = $student->created_at;
        $originalUpdatedAt = $student->updated_at;
        $this->travel(1)->minutes();
        $this->actingAs($admin)->patch('/admin/approvals/'.$student->id)->assertRedirect(route('admin.approvals.index'));
        $student->refresh();
        $this->assertSame(ApprovalStatus::Approved, $student->approval_status);
        $this->assertSame($admin->id, $student->approved_by);
        $this->assertNotNull($student->approved_at);
        $approvedAt = $student->approved_at;
        $updatedAt = $student->updated_at;
        $this->assertTrue($createdAt->equalTo($student->created_at));
        $this->assertTrue($updatedAt->greaterThan($originalUpdatedAt));
        $this->assertTrue($approvedAt->equalTo($updatedAt));
        Notification::assertSentTo($student, AccountApproved::class);
        $this->travel(1)->hours();
        $this->patch('/admin/approvals/'.$student->id)->assertRedirect();
        $student->refresh();
        $this->assertTrue($approvedAt->equalTo($student->approved_at));
        $this->assertTrue($createdAt->equalTo($student->created_at));
        $this->assertTrue($updatedAt->equalTo($student->updated_at));
        Notification::assertSentToTimes($student, AccountApproved::class, 1);
    }

    public function test_unverified_user_cannot_be_approved_even_by_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->unverified()->create();
        $this->actingAs($admin)->patch('/admin/approvals/'.$student->id)->assertSessionHasErrors('user');
        $this->assertSame(ApprovalStatus::Pending, $student->fresh()->approval_status);
        Notification::assertNothingSent();
    }

    public function test_approval_notification_waits_for_commit_and_is_discarded_on_rollback(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        DB::beginTransaction();
        app(ApproveUser::class)->approve($student, $admin);
        Notification::assertNothingSent();
        DB::rollBack();
        $this->assertSame(ApprovalStatus::Pending, $student->fresh()->approval_status);
        Notification::assertNothingSent();
        DB::beginTransaction();
        app(ApproveUser::class)->approve($student, $admin);
        Notification::assertNothingSent();
        DB::commit();
        Notification::assertSentToTimes($student, AccountApproved::class, 1);
    }

    public function test_approval_action_rejects_student_actor_independently_of_routes(): void
    {
        $student = User::factory()->approved()->create();
        $target = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(ApproveUser::class)->approve($target, $student);
    }

    public function test_login_remember_logout_and_rate_limit(): void
    {
        $user = User::factory()->approved()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => 1])
            ->assertRedirect('/home')->assertCookie(Auth::guard()->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/home')->assertRedirect('/login');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'wrong@example.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'wrong@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_remember_cookie_does_not_bypass_pending_status(): void
    {
        $user = User::factory()->create();
        $guard = Auth::guard();
        $cookie = $guard->getRecallerName();
        $this->withCookie($cookie, $user->id.'|'.$user->remember_token.'|'.$user->password)
            ->get('/home')->assertRedirect(route('approval.pending'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_reset_uses_fortify_and_preserves_permissions(): void
    {
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', __('passwords.sent'));
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertRedirect('/login');
        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame(ApprovalStatus::Pending, $user->approval_status);
        $this->post('/reset-password', [
            'email' => $user->email, 'token' => $token,
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password'])->assertRedirect('/home');
        $this->get('/home')->assertRedirect(route('approval.pending'));
    }

    public function test_invalid_and_expired_password_reset_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);
        $input = ['email' => $user->email, 'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->post('/reset-password', $input + ['token' => 'wrong'])->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post('/reset-password', $input + ['token' => $token])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_auth_views_render(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs(User::factory()->admin()->create())->get('/user/confirm-password')->assertOk();
    }

    public function test_admin_command_creates_verified_approved_account_without_fixed_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Név', 'Minta Admin')
            ->expectsQuestion('Emailcím', 'ADMIN@example.test')
            ->expectsQuestion('Jelszó', 'admin-test-password')
            ->expectsQuestion('Jelszó megerősítése', 'admin-test-password')
            ->assertSuccessful();
        $admin = User::sole();
        $this->assertNotNull($admin->created_at);
        $this->assertNotNull($admin->updated_at);
        $this->assertTrue($admin->created_at->equalTo($admin->updated_at));
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isApproved());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertNotNull($admin->approved_at);
        $this->assertNull($admin->approved_by);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertTrue(Hash::check('admin-test-password', $admin->password));
    }

    public function test_admin_command_does_not_promote_existing_student(): void
    {
        $student = User::factory()->create();
        $this->artisan('app:create-admin')
            ->expectsQuestion('Név', 'Minta Admin')
            ->expectsQuestion('Emailcím', $student->email)
            ->expectsQuestion('Jelszó', 'admin-test-password')
            ->expectsQuestion('Jelszó megerősítése', 'admin-test-password')
            ->assertFailed();
        $this->assertSame(Role::Student, $student->fresh()->role);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_verification_admin_notification_is_discarded_on_rollback(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->unverified()->create();
        DB::beginTransaction();
        $student->markEmailAsVerified();
        event(new Verified($student));
        Notification::assertNothingSent();
        DB::rollBack();
        Notification::assertNothingSent();
        $this->assertFalse($student->fresh()->hasVerifiedEmail());
    }

    public function test_state_changing_requests_require_csrf_outside_test_bypass(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->app['env'] = 'local';
        $this->actingAs($admin)->patch('/admin/approvals/'.$student->id)->assertStatus(419);
        $this->post('/logout')->assertStatus(419);
        $this->assertSame(ApprovalStatus::Pending, $student->fresh()->approval_status);
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        $student = User::factory()->unverified()->create();
        $this->actingAs($student);
        for ($i = 0; $i < 6; $i++) {
            $this->post('/email/verification-notification')->assertRedirect();
        }
        $this->post('/email/verification-notification')->assertStatus(429);
        Notification::assertSentToTimes($student, VerifyEmail::class, 6);
    }

    public function test_admin_command_rejects_password_mismatch(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Név', 'Minta Admin')
            ->expectsQuestion('Emailcím', 'admin@example.test')
            ->expectsQuestion('Jelszó', 'admin-test-password')
            ->expectsQuestion('Jelszó megerősítése', 'different-password')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_notification_templates_render_in_hungarian_with_expected_links(): void
    {
        $user = User::factory()->create();
        $messages = [
            (new VerifyEmail)->toMail($user),
            (new ResetPassword('sample-reset-token'))->toMail($user),
            (new AccountApproved)->toMail($user),
            (new UserAwaitingApproval($user))->toMail($user),
        ];
        foreach ($messages as $message) {
            $html = (string) $message->render();
            $this->assertStringContainsString('Kedves', $html);
            $this->assertStringContainsString('Ha a(z)', $html);
            $this->assertStringNotContainsString('If you&#039;re having trouble', $html);
        }
        $this->assertStringContainsString('/email/verify/'.$user->id, $messages[0]->actionUrl);
        $this->assertStringContainsString('/reset-password/sample-reset-token', $messages[1]->actionUrl);
        $this->assertSame(route('home'), $messages[2]->actionUrl);
        $this->assertSame(route('admin.approvals.index'), $messages[3]->actionUrl);
    }
}
