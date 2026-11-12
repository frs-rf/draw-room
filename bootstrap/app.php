<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Support serverless deployment environments (e.g. Vercel) where root storage is read-only
if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || env('VERCEL') || isset($_ENV['LARAVEL_STORAGE_PATH'])) {
    $app->useStoragePath(env('LARAVEL_STORAGE_PATH', '/tmp/storage'));
}

return $app;
