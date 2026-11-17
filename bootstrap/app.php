<?php

use App\Services\Fuseki\FusekiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A catalogue that cannot reach Fuseki is unavailable, not a generic
        // server error. The dedicated view tells the operator what to run.
        $exceptions->render(function (FusekiException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $exception->getMessage()], 503);
            }

            return response()->view('errors.fuseki', [
                'message' => $exception->getMessage(),
            ], 503);
        });
    })->create();
