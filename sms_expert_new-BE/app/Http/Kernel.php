<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware will be run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // Global middleware
    ];

    /**
     * The application's route middleware groups.
     *
     * These middleware groups may be applied to your routes.
     *
     * @var array
     */
    protected $middlewareGroups = [

    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to specific routes.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'check.session' => \App\Http\Middleware\CustomerMiddleware::class, 
        'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'campaign' => \App\Http\Middleware\CampaignMiddleware::class,
        'check.maintenance' => \App\Http\Middleware\CheckMaintenanceMode::class,
    ];
}
