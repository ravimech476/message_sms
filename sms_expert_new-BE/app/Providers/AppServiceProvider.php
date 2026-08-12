<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Database\LegacyMySqlGrammar;
use App\Services\Queue\RabbitMQService;
use App\Services\InboundSmsProcessor;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Skip RabbitMQ connection during migrations
        if ($this->app->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];
            foreach ($argv as $arg) {
                if (strpos($arg, 'migrate') !== false) {
                    RabbitMQService::skipConnection(true);
                    break;
                }
            }
        }

        // Register InboundSmsProcessor as singleton
        $this->app->singleton(InboundSmsProcessor::class, function ($app) {
            return new InboundSmsProcessor();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Swap Laravel 11's default MySQL schema grammar for one compatible with MySQL 5.1.
        // The default queries information_schema.columns.generation_expression (MySQL 5.7+ only),
        // which fails on the legacy DB server. See App\Database\LegacyMySqlGrammar.
        $connection = DB::connection();
        if ($connection->getDriverName() === 'mysql') {
            $connection->setSchemaGrammar(new LegacyMySqlGrammar);
        }
    }
}
