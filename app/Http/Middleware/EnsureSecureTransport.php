<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class EnsureSecureTransport
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        config(['session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax', 'app.debug' => false]);
        URL::forceScheme('https');
        if (! $request->isSecure()) {
            // Inspect the wire method, not a body-supplied _method override.
            if (! in_array($request->getRealMethod(), ['GET', 'HEAD'], true)) {
                return new Response('HTTPS is required.', 400);
            }
            $host = parse_url(config('app.url'), PHP_URL_HOST);
            if (! is_string($host) || $host === '') {
                return new Response('Secure site URL is unavailable.', 503);
            }
            $port = parse_url(config('app.url'), PHP_URL_PORT);
            $authority = $host.($port !== null && ! in_array($port, [80, 443], true) ? ':'.$port : '');

            return redirect()->away('https://'.$authority.$request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
