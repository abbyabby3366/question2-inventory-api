<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Applies the "api" rate limiter (defined in AppServiceProvider) to every /api route.
        $middleware->throttleApi();

        // This is an API-only app with no login page: unauthenticated API calls get a JSON 401
        // instead of a redirect to a non-existent "login" route.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Always answer /api/* with JSON errors (401/403/404/422/429...), even when the client
        // forgets the "Accept: application/json" header, instead of redirecting to a login page.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
