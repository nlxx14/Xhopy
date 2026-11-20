<?php

namespace App\Domain\Identity\Http\Middleware;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side least-privilege role enforcement ("role:admin", "role:staff,admin").
 * Denies by default whenever no role matches.
 */
final class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $roles === []) {
            abort(403);
        }

        $allowed = array_filter(
            array_map(fn (string $role): ?UserRole => UserRole::tryFrom(trim($role)), $roles),
        );

        if (! in_array($user->role, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
