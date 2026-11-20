<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Models\AccountAuditLog;
use App\Domain\Identity\Services\EmailVerificationCodeService;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_email_verification_screen_can_be_rendered()
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
    }

    public function test_unverified_users_are_redirected_to_the_email_verification_prompt(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('appearance.edit'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_email_can_be_verified()
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
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_user_id(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 123, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verified_user_is_redirected_to_dashboard_from_verification_prompt(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        Event::assertNotDispatched(Verified::class);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        Event::assertNotDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_can_be_verified_using_six_digit_code_sent_to_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'student@gmail.com',
        ]);

        $user->sendEmailVerificationNotification();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class,
            function (VerifyEmail $notification) use ($user): bool {
                $mail = $notification->toMail($user);

                return str_contains($mail->subject ?? '', (string) $user->issuedVerificationCode);
            },
        );

        $sentCode = $user->issuedVerificationCode;
        $this->assertNotNull($sentCode);
        $this->assertSame(1, preg_match('/^\d{6}$/', (string) $sentCode));
        $this->assertNotSame($sentCode, $user->fresh()->email_verification_code);

        $response = $this->actingAs($user)
            ->post(route('verification.code.verify'), [
                'code' => $sentCode,
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->email_verification_code);
        $this->assertNull($user->email_verification_code_expires_at);
        $this->assertTrue(
            AccountAuditLog::query()
                ->where('user_id', $user->id)
                ->where('event', AuditEvent::EmailVerified)
                ->exists(),
        );
    }

    public function test_email_is_not_verified_with_wrong_or_expired_six_digit_code(): void
    {
        $user = User::factory()->unverified()->create();
        $code = app(EmailVerificationCodeService::class)->issue($user);

        // Wrong 6-digit code
        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.code.verify'), [
                'code' => $code === '000000' ? '999999' : '000000',
            ])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());

        // Expired code
        $user->forceFill([
            'email_verification_code_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($user)
            ->from(route('verification.notice'))
            ->post(route('verification.code.verify'), [
                'code' => $code,
            ])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
