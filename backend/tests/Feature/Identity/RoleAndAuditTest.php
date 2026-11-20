<?php

use App\Domain\Identity\Enums\AuditEvent;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\AccountAuditLog;
use App\Domain\Identity\Services\AccountAuditRecorder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', fn () => 'admin-ok');
    Route::middleware(['web', 'auth', 'role:staff,admin'])->get('/_test/backoffice', fn () => 'staff-ok');
});

test('registration and profile updates cannot escalate user role via mass assignment', function () {
    $this->post(route('register.store'), [
        'name' => 'Regular Shopper',
        'email' => 'shopper@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ])->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'shopper@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Customer);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Updated Shopper',
            'email' => 'shopper@example.com',
            'phone' => '+63 917 123 4567',
            'role' => 'admin',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->role)->toBe(UserRole::Customer)
        ->and($user->phone)->toBe('+63 917 123 4567');
});

test('role middleware and gates enforce least-privilege authorization', function () {
    $customer = User::factory()->create();
    $staff = User::factory()->staff()->create();
    $admin = User::factory()->admin()->create();

    expect(Gate::forUser($customer)->allows('access-backoffice'))->toBeFalse()
        ->and(Gate::forUser($customer)->allows('access-admin'))->toBeFalse()
        ->and(Gate::forUser($staff)->allows('access-backoffice'))->toBeTrue()
        ->and(Gate::forUser($staff)->allows('access-admin'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('access-backoffice'))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('access-admin'))->toBeTrue();

    $this->actingAs($customer)->get('/_test/backoffice')->assertForbidden();
    $this->actingAs($customer)->get('/_test/admin-only')->assertForbidden();

    $this->actingAs($staff)->get('/_test/backoffice')->assertOk();
    $this->actingAs($staff)->get('/_test/admin-only')->assertForbidden();

    $this->actingAs($admin)->get('/_test/backoffice')->assertOk();
    $this->actingAs($admin)->get('/_test/admin-only')->assertOk();
});

test('audit recorder strips sensitive keys from metadata and records auth and security events', function () {
    $user = User::factory()->create();

    /** @var AccountAuditRecorder $recorder */
    $recorder = app(AccountAuditRecorder::class);
    $log = $recorder->record(AuditEvent::ProfileUpdated, $user, [
        'safe_field' => 'ok',
        'password' => 'should-be-stripped',
        'two_factor_secret' => 'should-be-stripped',
        'token' => 'should-be-stripped',
    ]);

    expect($log->metadata)->toBe(['safe_field' => 'ok']);

    // Failed login records AuditEvent::LoginFailed
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(
        AccountAuditLog::query()
            ->where('user_id', $user->id)
            ->where('event', AuditEvent::LoginFailed)
            ->exists()
    )->toBeTrue();

    // Successful login records AuditEvent::Login
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect(
        AccountAuditLog::query()
            ->where('user_id', $user->id)
            ->where('event', AuditEvent::Login)
            ->exists()
    )->toBeTrue();
});

test('revoking other browser sessions requires the current password and records an audit event', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('security.sessions.destroy'), [
            'current_password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('security.sessions.destroy'), [
            'current_password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(
        AccountAuditLog::query()
            ->where('user_id', $user->id)
            ->where('event', AuditEvent::OtherSessionsRevoked)
            ->exists()
    )->toBeTrue();
});
