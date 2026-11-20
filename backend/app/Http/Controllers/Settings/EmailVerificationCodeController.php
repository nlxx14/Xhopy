<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Identity\Services\EmailVerificationCodeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmailVerificationCodeController extends Controller
{
    /**
     * Verify the authenticated user's email address using a 6-digit verification code.
     */
    public function __invoke(
        Request $request,
        EmailVerificationCodeService $verificationCodes,
    ): RedirectResponse {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        if (! $verificationCodes->verify($user, (string) $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => __('The verification code is invalid or has expired. Please request a new code.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Email address verified.')]);

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
