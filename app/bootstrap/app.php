<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Modules\Shared\Exceptions\ApiExceptionRenderer;
use App\Modules\Shared\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This API has no login page: an unauthenticated request gets 401 JSON, never a redirect.
        $middleware->redirectGuestsTo(fn () => null);

        // Security headers on every response
        $middleware->append(SecurityHeaders::class);

        // General rate limit for all API routes (the "api" limiter is defined in AppServiceProvider)
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One error format for every API response: {"error": {"code", "message", "details"?}}
        $exceptions->render(fn (Throwable $e, Request $request) => (new ApiExceptionRenderer())($e, $request));
    })->create();
