<?php

namespace App\Traits;

use App\Services\Logging\CronLogService;

/**
 * Trait for logging cron job activity
 *
 * Usage in Command class:
 *
 * use App\Traits\LogsCronActivity;
 *
 * class MyCommand extends Command
 * {
 *     use LogsCronActivity;
 *
 *     public function handle()
 *     {
 *         $this->initCronLog(); // or $this->initCronLog('CustomName')
 *         $this->cronStart(['param' => 'value']);
 *
 *         // Your logic here
 *         $this->cronInfo('Processing something', ['count' => 10]);
 *
 *         $this->cronEnd(['processed' => 100, 'failed' => 5]);
 *     }
 * }
 */
trait LogsCronActivity
{
    protected ?CronLogService $cronLog = null;

    /**
     * Initialize the cron logger
     *
     * @param string|null $cronName Custom name (defaults to class name)
     */
    protected function initCronLog(?string $cronName = null): void
    {
        $name = $cronName ?? class_basename(static::class);
        $this->cronLog = CronLogService::for($name);
    }

    /**
     * Get or create the cron logger
     */
    protected function getCronLog(): CronLogService
    {
        if (!$this->cronLog) {
            $this->initCronLog();
        }
        return $this->cronLog;
    }

    /**
     * Log cron start
     */
    protected function cronStart(array $context = []): void
    {
        $this->getCronLog()->start($context);
    }

    /**
     * Log cron end
     */
    protected function cronEnd(array $summary = []): void
    {
        $this->getCronLog()->end($summary);
    }

    /**
     * Log cron failure
     */
    protected function cronFailed(string $error, array $context = []): void
    {
        $this->getCronLog()->failed($error, $context);
    }

    /**
     * Log info message
     */
    protected function cronInfo(string $message, array $context = []): void
    {
        $this->getCronLog()->info($message, $context);
    }

    /**
     * Log warning message
     */
    protected function cronWarning(string $message, array $context = []): void
    {
        $this->getCronLog()->warning($message, $context);
    }

    /**
     * Log error message
     */
    protected function cronError(string $message, array $context = []): void
    {
        $this->getCronLog()->error($message, $context);
    }

    /**
     * Log debug message
     */
    protected function cronDebug(string $message, array $context = []): void
    {
        $this->getCronLog()->debug($message, $context);
    }

    /**
     * Get the log file path
     */
    protected function getCronLogPath(): string
    {
        return $this->getCronLog()->getPath();
    }
}
