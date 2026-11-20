<?php

namespace App\Models;

use App\Domain\Customer\Models\CustomerAddress;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\AccountAuditLog;
use App\Domain\Identity\Services\EmailVerificationCodeService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string|null $email_verification_code
 * @property Carbon|null $email_verification_code_expires_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'email_verification_code', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Transient (non-persisted) copy of the most recently issued 6-digit verification code
     * used when rendering the outgoing VerifyEmail notification message.
     */
    public ?string $issuedVerificationCode = null;

    /**
     * Default attributes. Role is never mass-assignable from request payloads.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'customer',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Issue a 6-digit verification code and email it (along with the signed verification link) to the user.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationCodeService::class)->send($this);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)
            ->orderByDesc('is_default_shipping')
            ->orderByDesc('is_default_billing')
            ->orderByDesc('updated_at');
    }

    /**
     * @return HasMany<AccountAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AccountAuditLog::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaffOrAdmin(): bool
    {
        return $this->role->canAccessBackOffice();
    }

    /**
     * Explicit server-side role assignment (bypasses mass-assignment intentionally).
     */
    public function assignRole(UserRole $role): self
    {
        $this->role = $role;
        $this->save();

        return $this;
    }
}
