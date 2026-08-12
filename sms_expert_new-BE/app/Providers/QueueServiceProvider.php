<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;

class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure the database queue connection for emails
        Queue::connection('database')->setConnectionName('emails');
        
        // Set default queue for email jobs
        config(['queue.connections.emails' => [
            'driver' => 'database',
            'table' => 'email_jobs',
            'queue' => 'emails',
            'retry_after' => 90,
        ]]);
    }
}
