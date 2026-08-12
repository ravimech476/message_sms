<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\Handler;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'log.activity' => \App\Http\Middleware\LogUserActivity::class,
            'api.error.monitor' => \App\Http\Middleware\ApiErrorMonitor::class,
            'admin.permission' => \App\Http\Middleware\CheckAdminPermission::class,
        ]);
        
        // Apply activity logging to web routes
        $middleware->web(append: [
            \App\Http\Middleware\LogUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handler will be automatically used
    })->create();
