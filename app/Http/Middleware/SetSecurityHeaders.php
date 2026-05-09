<?php

namespace Pterodactyl\Http\Middleware;

use Closure;

use Illuminate\Http\Request;

class SetSecurityHeaders
{
    /**
     * Security headers applied at the application layer as defense-in-depth.
     * These supplement (not replace) any web server headers from Nginx/Caddy.
     * Duplicate headers are skipped automatically in the handle() method.
     */
    private static array $headers = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'X-XSS-Protection' => '0',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        'Strict-Transport-Security' => 'max-age=63072000; includeSubDomains; preload',
    ];

    /**
     * Enforces some basic security headers on all responses returned by the software.
     * If a header has already been set in another location within the code it will be
     * skipped over here.
     *
     * @param (Closure(mixed): \Illuminate\Http\Response) $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        foreach (static::$headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}
