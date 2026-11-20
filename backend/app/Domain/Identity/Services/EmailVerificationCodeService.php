<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Issues and verifies cryptographically random 6-digit email verification codes.
 * Codes are stored as HMAC-SHA256 hashes bound to the user's email and expire after 15 minutes.
 */
final class EmailVerificationCodeService
{
    public const EXPIRES_IN_MINUTES = 15;

    public function __construct(private readonly AccountAuditRecorder $audit) {}

    /**
     * Issue a new 6-digit verification code, persist its hash + expiry, and return the plaintext code.
     */
    public function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'email_verification_code' => $this->hash($user, $code),
            'email_verification_code_expires_at' => now()->addMinutes(self::EXPIRES_IN_MINUTES),
        ])->save();

        $user->issuedVerificationCode = $code;

        return $code;
    }

    /**
     * Issue a new 6-digit verification code and send the email notification to the user.
     */
    public function send(User $user): string
    {
        $code = $this->issue($user);

        $user->notify(new VerifyEmail);

        return $code;
    }

    /**
     * Build the verification email containing both the 6-digit verification code and the signed link.
     */
    public function toMailMessage(User $user, string $verificationUrl): MailMessage
    {
        $code = $user->issuedVerificationCode ?? $this->issue($user);

        return (new MailMessage)
            ->subject(__('[:app] Your email verification code is :code', [
                'app' => config('app.name', 'XHopy'),
                'code' => $code,
            ]))
            ->greeting(__('Verify your email address'))
            ->line(__('Enter the 6-digit verification code below on the XHopy verification screen to confirm your email address:'))
            ->line('**'.$code.'**')
            ->line(__('This verification code expires in :minutes minutes. You can also verify by clicking the button below:', [
                'minutes' => self::EXPIRES_IN_MINUTES,
            ]))
            ->action(__('Verify Email Address'), $verificationUrl)
            ->line(__('If you did not create an account on XHopy, no further action is required.'));
    }

    /**
     * Verify a submitted 6-digit code in constant time and mark the user's email verified on match.
     */
    public function verify(User $user, string $submittedCode): bool
    {
        $storedHash = $user->email_verification_code;
        $expiresAt = $user->email_verification_code_expires_at;

        if ($storedHash === null || $storedHash === '' || $expiresAt === null || $expiresAt->isPast()) {
            return false;
        }

        $candidateHash = $this->hash($user, trim($submittedCode));

        if (! hash_equals($storedHash, $candidateHash)) {
            return false;
        }

        $wasUnverified = ! $user->hasVerifiedEmail();

        $user->forceFill([
            'email_verified_at' => $wasUnverified ? now() : $user->email_verified_at,
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ])->save();

        $user->issuedVerificationCode = null;

        if ($wasUnverified) {
            event(new Verified($user));
            $this->audit->record(AuditEvent::EmailVerified, $user, ['method' => 'code']);
        }

        return true;
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac(
            'sha256',
            strtolower($user->email).'|'.$code,
            (string) config('app.key'),
        );
    }
}
