<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'track.activity' => \App\Http\Middleware\TrackUserActivity::class,
        ]);
        
        // Exempt logout-beacon from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/logout-beacon',
        ]);
        
        // Apply activity tracking only if enabled
        if (env('ENABLE_AUTO_LOGOUT', true)) {
            $middleware->web(append: [
                \App\Http\Middleware\TrackUserActivity::class,
            ]);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
