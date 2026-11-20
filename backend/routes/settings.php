<?php

use App\Http\Controllers\Settings\AddressController;
use App\Http\Controllers\Settings\EmailVerificationCodeController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::post('email/verify-code', EmailVerificationCodeController::class)
        ->middleware('throttle:6,1')
        ->name('verification.code.verify');

    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('settings/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('settings/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::patch('settings/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.default');
    Route::delete('settings/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::delete('settings/security/sessions', [SecurityController::class, 'destroyOtherSessions'])
        ->middleware('throttle:6,1')
        ->name('security.sessions.destroy');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
