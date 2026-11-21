<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Attach request correlation ID and baseline HTTP security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingRequestId = $request->header('X-Request-Id');
        $requestId = is_string($incomingRequestId) && preg_match('/^[A-Za-z0-9._\-]{8,64}$/', $incomingRequestId) === 1
            ? $incomingRequestId
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (app()->isProduction() || $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $response->headers->has('Content-Security-Policy') && ! is_file(public_path('hot'))) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data: https:; font-src 'self' data: https://fonts.bunny.net; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; script-src 'self' 'unsafe-inline'; connect-src 'self'",
            );
        }

        return $response;
    }
}
