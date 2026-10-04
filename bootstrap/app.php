<?php

use App\Http\Middleware\EnsureSecureTransport;
use App\Http\Middleware\TrustConfiguredProxies;
use App\Support\Diagnostics\ErrorDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->remove(TrustProxies::class);
        $middleware->prepend([
            TrustConfiguredProxies::class,
            EnsureSecureTransport::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! ErrorDetails::enabled() || $request->expectsJson()) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

            if ($status < 500) {
                return null;
            }

            return response()
                ->view('errors.diagnostics', ['exception' => $exception, 'status' => $status], $status)
                ->header('Cache-Control', 'private, no-store');
        });
    })->create();
