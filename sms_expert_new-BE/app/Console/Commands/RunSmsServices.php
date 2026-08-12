<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class RunSmsServices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:run 
                            {service : Service to run (all|sms|dlr|both)}
                            {--workers=1 : Number of SMS workers}
                            {--daemon : Run as daemon (continuous)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run SMS and DLR processing services';

    private $processes = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $service = $this->argument('service');
        $workers = $this->option('workers');
        $daemon = $this->option('daemon');
        
        $this->info("=== SMS Services Runner ===");
        $this->info("Service: {$service}");
        $this->info("Workers: {$workers}");
        $this->info("Mode: " . ($daemon ? 'Daemon' : 'Single Run'));
        $this->info("");
        
        // Register signal handlers for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }
        
        try {
            switch ($service) {
                case 'all':
                case 'both':
                    $this->runBothServices($workers, $daemon);
                    break;
                    
                case 'sms':
                    $this->runSmsService($workers, $daemon);
                    break;
                    
                case 'dlr':
                    $this->runDlrService($daemon);
                    break;
                    
                default:
                    $this->error("Invalid service: {$service}");
                    $this->info("Valid options: all, both, sms, dlr");
                    return 1;
            }
            
            // If daemon mode, keep running
            if ($daemon && !empty($this->processes)) {
                $this->monitorProcesses();
            }
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->stopAllProcesses();
            return 1;
        }
        
        return 0;
    }

    /**
     * Run both SMS and DLR services
     */
    private function runBothServices($workers, $daemon)
    {
        $this->info("Starting SMS and DLR services...\n");
        
        // Start SMS workers
        for ($i = 1; $i <= $workers; $i++) {
            $this->startProcess(
                "SMS Worker {$i}",
                ['sms:process-queue'],
                $daemon
            );
        }
        
        // Start DLR processor
        $this->startProcess(
            "DLR Processor",
            ['smpp:dlr-receiver', '--continuous', '--rabbitmq'],
            true  // Always run DLR in daemon mode
        );
    }

    /**
     * Run SMS service only
     */
    private function runSmsService($workers, $daemon)
    {
        $this->info("Starting SMS processing service...\n");
        
        for ($i = 1; $i <= $workers; $i++) {
            $this->startProcess(
                "SMS Worker {$i}",
                ['sms:process-queue'],
                $daemon
            );
        }
    }

    /**
     * Run DLR service only
     */
    private function runDlrService($daemon)
    {
        $this->info("Starting DLR processing service...\n");
        
        $this->startProcess(
            "DLR Processor",
            ['smpp:dlr-receiver', '--continuous', '--rabbitmq'],
            $daemon
        );
    }

    /**
     * Start a process
     */
    private function startProcess($name, $command, $daemon = false)
    {
        $phpBinary = (new PhpExecutableFinder)->find();
        $artisan = base_path('artisan');
        
        $fullCommand = array_merge([$phpBinary, $artisan], $command);
        
        if ($daemon) {
            $process = new Process($fullCommand);
            $process->setTimeout(null);
            $process->start();
            
            $this->processes[$name] = $process;
            $this->info("✓ Started: {$name} (PID: " . $process->getPid() . ")");
            
            // Don't wait here - let monitorProcesses handle it
            $this->info("  Process started in background");
        } else {
            // Run synchronously
            $this->info("Running: {$name}");
            $process = new Process($fullCommand);
            $process->setTimeout(null);
            $process->run(function ($type, $buffer) {
                $this->line($buffer);
            });
        }
    }

    /**
     * Monitor running processes
     */
    private function monitorProcesses()
    {
        $this->info("\nMonitoring processes. Press Ctrl+C to stop all.\n");
        
        while (!empty($this->processes)) {
            foreach ($this->processes as $name => $process) {
                // Check for output
                $output = $process->getIncrementalOutput();
                if (!empty($output)) {
                    $lines = explode("\n", trim($output));
                    foreach ($lines as $line) {
                        if (!empty($line)) {
                            $this->line("[{$name}] {$line}");
                        }
                    }
                }
                
                $errorOutput = $process->getIncrementalErrorOutput();
                if (!empty($errorOutput)) {
                    $lines = explode("\n", trim($errorOutput));
                    foreach ($lines as $line) {
                        if (!empty($line)) {
                            $this->error("[{$name}] {$line}");
                        }
                    }
                }
                
                // Check if process is still running
                if (!$process->isRunning()) {
                    $exitCode = $process->getExitCode();
                    
                    if ($exitCode !== 0) {
                        $this->error("Process '{$name}' stopped with exit code: {$exitCode}");
                        
                        // Restart if it crashed
                        $this->warn("Restarting '{$name}'...");
                        $process->restart();
                    } else {
                        $this->info("Process '{$name}' completed successfully");
                        unset($this->processes[$name]);
                    }
                }
            }
            
            if (!empty($this->processes)) {
                usleep(100000); // Check every 100ms for more responsive output
            }
        }
        
        $this->info("All processes completed.");
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown($signal)
    {
        $this->info("\nShutdown signal received. Stopping all processes...");
        $this->stopAllProcesses();
        exit(0);
    }

    /**
     * Stop all processes
     */
    private function stopAllProcesses()
    {
        foreach ($this->processes as $name => $process) {
            if ($process->isRunning()) {
                $this->info("Stopping: {$name}");
                $process->stop(5);
            }
        }
        
        $this->info("All processes stopped.");
    }
}
