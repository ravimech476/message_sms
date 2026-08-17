<?php

namespace App\Services\Logging;

/**
 * Per-component, date-bucketed logging — mirrors sms_expert's SmppLogger /
 * RabbitMQLogService / ApiLogService, collapsed into one reusable class.
 *
 * Writes to  storage/logs/{YYYY-MM-DD}/{folder}/{name}.log  so operators can
 * tail one component (or one queue) without grepping the whole laravel.log,
 * and purge whole date folders together (see logs:cleanup).
 *
 *   ComponentLogger::smpp()->info('bind OK', ['host' => $h]);
 *   ComponentLogger::rabbitmq('sms.outbound')->info('published', [...]);
 *   ComponentLogger::api()->info('send request', [...]);
 *
 * @author Anand Karthik
 */
class ComponentLogger
{
    protected string $path;

    public function __construct(string $folder, string $name)
    {
        $date   = date('Y-m-d');
        $folder = $this->sanitize($folder);
        $name   = $this->sanitize($name);
        $this->path = storage_path("logs/{$date}/{$folder}/{$name}.log");

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
            @chmod($dir, 0777); // php-fpm (nobody) + workers (root) must both write
        }
    }

    public static function smpp(string $provider = 'vonage'): self
    {
        return new self('smpp', $provider);
    }

    public static function rabbitmq(string $queue): self
    {
        return new self('rabbitmq', $queue);
    }

    public static function api(string $name = 'requests'): self
    {
        return new self('api', $name);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    protected function write(string $level, string $message, array $context): void
    {
        $ts  = date('Y-m-d H:i:s');
        $ctx = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        @file_put_contents($this->path, "[{$ts}] {$level}: {$message}{$ctx}" . PHP_EOL, FILE_APPEND | LOCK_EX);
        @chmod($this->path, 0666);
    }

    protected function sanitize(string $s): string
    {
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $s);
    }
}
