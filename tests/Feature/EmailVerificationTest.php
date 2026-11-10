<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_verification_notice_is_shown_to_unverified_users(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertOk();
    }

    public function test_unverified_user_can_use_the_dashboard_without_verifying(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_phone_only_user_is_sent_on_from_the_notice(): void
    {
        $user = User::factory()->unverified()->create(['email' => null, 'phone' => '+21622123456']);

        $this->actingAs($user)->get('/verify-email')->assertRedirect(route('dashboard'));
    }

    public function test_verified_user_is_sent_on_from_the_notice(): void
    {
        $this->actingAs(User::factory()->create())->get('/verify-email')->assertRedirect(route('dashboard'));
    }

    public function test_verified_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard').'?verified=1');
    }

    public function test_verification_notification_is_queued(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new QueuedVerifyEmail);
    }

    public function test_registration_sends_no_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'Fresh User',
            'email' => 'fresh@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $user = User::where('email', 'fresh@example.com')->firstOrFail();

        Notification::assertNotSentTo($user, QueuedVerifyEmail::class);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
