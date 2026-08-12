<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CronJobLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MonitorController extends Controller
{
    /**
     * Get date-wise log path
     * Creates folder if it doesn't exist
     *
     * @param string $filename
     * @param string|null $date Optional date (defaults to today)
     * @return string
     */
    protected function getDateWiseLogPath(string $filename, ?string $date = null): string
    {
        $date = $date ?? date('Y-m-d');
        $dateFolder = storage_path('logs/' . $date);
        
        // Create date folder if it doesn't exist
        if (!is_dir($dateFolder)) {
            mkdir($dateFolder, 0755, true);
        }
        
        return $dateFolder . '/' . $filename;
    }

    /**
     * Map cron commands to their log file names
     *
     * @return array
     */
    protected function getCronLogFileMapping(): array
    {
        return [
            'sms:process-scheduled' => 'scheduled-sms-processor.log',
            'emails:send-schedule' => 'emails-send-schedule.log',
            'sms:update-pricing' => 'sms-pricing-update.log',
            'wallet:send-reminders' => 'wallet-reminders.log',
            'delivery-receipt:push' => 'delivery-receipt-default.log',
            'virtualnumbers:sync' => 'virtualnumbers-sync.log',
            'nexmo:fetch-delivery-reports' => 'nexmo-delivery-reports.log',
            'nexmo:fetch-delivery-reports-daily' => 'nexmo-delivery-reports-daily.log',
            'db:tidy' => 'db-tidy.log',
            'sms:xml-gateway' => 'xml-to-sms-gateway.log',
            'report:daily-stats' => 'daily-stats-report.log',
            'report:virtual-number-expiry' => 'virtual-number-expiry-report.log',
            'alert:funds-check' => 'funds-alert-check.log',
            'smpp:regular-checks' => 'smpp-regular-checks.log',
            'sms:heartbeat' => 'sms-heartbeat.log',
            'pooledvirts:monitor' => 'pooledvirts-monitor.log',
            'urlforward:process' => 'urlforward-daemon.log',
            'notifications:process-scheduled' => 'scheduled-notifications.log',
        ];
    }

    /**
     * Define configured supervisor processes
     */
    protected function getConfiguredSupervisorProcesses(): array
    {
        return [
            [
                'name' => 'sms_process_queue',
                'command' => 'php artisan sms:process-queue',
                'description' => 'SMS Process Queue - Processes outbound SMS messages'
            ],
            [
                'name' => 'smpp_dlr_receiver',
                'command' => 'php artisan smpp:dlr-receiver',
                'description' => 'SMPP DLR Receiver - Receives delivery receipts'
            ],
            [
                'name' => 'rabbitmq_consume_emails',
                'command' => 'php artisan rabbitmq:consume-emails',
                'description' => 'RabbitMQ Email Consumer - Processes email queue'
            ],
            [
                'name' => 'smpp_monitor',
                'command' => 'php artisan smpp:monitor',
                'description' => 'SMPP Monitor - Monitors SMPP connections'
            ],
            [
                'name' => 'queue_inbound-sms',
                'command' => 'php artisan queue:inbound-sms',
                'description' => 'Inbound SMS Queue - Processes incoming SMS messages'
            ],
            [
                'name' => 'nexmo_delivery_queue',
                'command' => 'php artisan nexmo:process-delivery-queue --continuous',
                'description' => 'Nexmo Delivery Queue - Processes Nexmo delivery reports'
            ],
             [
                'name' => 'campaign:consume',
                'command' => 'php artisan campaign:consume',
                'description' => 'Campaign Update'
            ],
        ];
    }

    /**
     * Detect the server operating system
     */
    protected function detectOperatingSystem(): array
    {
        $osFamily = PHP_OS_FAMILY; // 'Windows', 'Linux', 'Darwin' (macOS), etc.
        $osName = PHP_OS; // More specific: 'WINNT', 'Linux', 'Darwin', etc.
        
        $osInfo = [
            'family' => $osFamily,
            'name' => $osName,
            'is_windows' => $osFamily === 'Windows',
            'is_linux' => $osFamily === 'Linux',
            'is_macos' => $osFamily === 'Darwin',
            'distribution' => null,
            'version' => null,
            'display_name' => $osFamily
        ];
        
        // For Linux, try to detect the distribution
        if ($osFamily === 'Linux') {
            $osInfo = array_merge($osInfo, $this->detectLinuxDistribution());
        } elseif ($osFamily === 'Windows') {
            $osInfo['display_name'] = 'Windows';
            $osInfo['distribution'] = 'Windows';
            // Get Windows version
            $winVer = php_uname('v');
            $osInfo['version'] = $winVer;
        } elseif ($osFamily === 'Darwin') {
            $osInfo['display_name'] = 'macOS';
            $osInfo['distribution'] = 'macOS';
        }
        
        return $osInfo;
    }

    /**
     * Detect Linux distribution
     */
    protected function detectLinuxDistribution(): array
    {
        $distribution = 'Linux';
        $version = '';
        $displayName = 'Linux';
        
        // Try to read /etc/os-release (works on most modern Linux distributions)
        if (file_exists('/etc/os-release')) {
            $osRelease = parse_ini_file('/etc/os-release');
            if ($osRelease) {
                $distribution = $osRelease['ID'] ?? 'linux';
                $version = $osRelease['VERSION_ID'] ?? '';
                $displayName = $osRelease['PRETTY_NAME'] ?? $osRelease['NAME'] ?? 'Linux';
            }
        }
        // Fallback: Try /etc/redhat-release for CentOS/RHEL
        elseif (file_exists('/etc/redhat-release')) {
            $content = file_get_contents('/etc/redhat-release');
            if (stripos($content, 'centos') !== false) {
                $distribution = 'centos';
                $displayName = trim($content);
            } elseif (stripos($content, 'red hat') !== false) {
                $distribution = 'rhel';
                $displayName = trim($content);
            } else {
                $distribution = 'redhat-based';
                $displayName = trim($content);
            }
        }
        // Fallback: Try /etc/debian_version for Debian/Ubuntu
        elseif (file_exists('/etc/debian_version')) {
            $distribution = 'debian';
            $version = trim(file_get_contents('/etc/debian_version'));
            $displayName = "Debian {$version}";
            
            // Check if it's Ubuntu
            if (file_exists('/etc/lsb-release')) {
                $lsbRelease = parse_ini_file('/etc/lsb-release');
                if (isset($lsbRelease['DISTRIB_ID']) && strtolower($lsbRelease['DISTRIB_ID']) === 'ubuntu') {
                    $distribution = 'ubuntu';
                    $version = $lsbRelease['DISTRIB_RELEASE'] ?? $version;
                    $displayName = $lsbRelease['DISTRIB_DESCRIPTION'] ?? "Ubuntu {$version}";
                }
            }
        }
        
        return [
            'distribution' => strtolower($distribution),
            'version' => $version,
            'display_name' => $displayName
        ];
    }

    /**
     * Get supervisor processes status
     */
    public function getSupervisorStatus()
    {
        $processes = [];
        $configuredProcesses = $this->getConfiguredSupervisorProcesses();
        $osInfo = $this->detectOperatingSystem();
        $supervisorAvailable = false;
        $supervisorOutput = null;
        $processManager = 'none';
        $processManagerStatus = 'not_available';
        
        try {
            if ($osInfo['is_windows']) {
                // Windows - Supervisor not available, show configured processes only
                $processManager = 'none';
                $processManagerStatus = 'windows_not_supported';
                
                foreach ($configuredProcesses as $configuredProcess) {
                    $processes[] = [
                        'name' => $configuredProcess['name'],
                        'status' => 'N/A',
                        'pid' => '-',
                        'uptime' => '-',
                        'start_time' => '-',
                        'description' => $configuredProcess['description'],
                        'command' => $configuredProcess['command']
                    ];
                }
            } elseif ($osInfo['is_linux']) {
                // Linux (Ubuntu, CentOS, etc.) - Try supervisorctl
                $processManager = 'supervisor';
                
                // Check if supervisorctl exists
                $supervisorPath = trim(shell_exec('which supervisorctl 2>/dev/null') ?? '');
                
                if (!empty($supervisorPath)) {
                    $supervisorOutput = shell_exec('supervisorctl status 2>&1');
                    
                    // Check if supervisor daemon is running
                    if ($supervisorOutput && 
                        !str_contains($supervisorOutput, 'refused') && 
                        !str_contains($supervisorOutput, 'no such file') &&
                        !str_contains($supervisorOutput, 'error')) {
                        
                        $supervisorAvailable = true;
                        $processManagerStatus = 'running';
                        
                        $lines = explode("\n", trim($supervisorOutput));
                        
                        foreach ($lines as $line) {
                            if (empty(trim($line))) continue;
                            
                            // Parse supervisor output
                            // Format: process_name   STATUS   pid 12345, uptime 1:23:45
                            preg_match('/^(\S+)\s+(\w+)\s+(.*)$/', $line, $matches);
                            
                            if (count($matches) >= 3) {
                                $processInfo = [
                                    'name' => $matches[1],
                                    'status' => $matches[2],
                                    'pid' => null,
                                    'uptime' => null,
                                    'start_time' => null,
                                    'description' => $this->getProcessDescription($matches[1], $configuredProcesses),
                                    'command' => $this->getProcessCommand($matches[1], $configuredProcesses)
                                ];
                                
                                // Extract PID and uptime
                                if (preg_match('/pid (\d+)/', $matches[3], $pidMatch)) {
                                    $processInfo['pid'] = $pidMatch[1];
                                }
                                
                                if (preg_match('/uptime (.+)$/', $matches[3], $uptimeMatch)) {
                                    $processInfo['uptime'] = trim($uptimeMatch[1]);
                                }
                                
                                // Get start time from cache or calculate
                                $cacheKey = 'supervisor_start_' . $processInfo['name'];
                                if ($processInfo['status'] === 'RUNNING') {
                                    if (!Cache::has($cacheKey)) {
                                        Cache::put($cacheKey, now(), 86400);
                                    }
                                    $startTime = Cache::get($cacheKey);
                                    $processInfo['start_time'] = Carbon::parse($startTime)->format('Y-m-d H:i:s');
                                } else {
                                    Cache::forget($cacheKey);
                                    $processInfo['start_time'] = '-';
                                }
                                
                                $processes[] = $processInfo;
                            }
                        }
                    } else {
                        // Supervisor installed but daemon not running
                        $processManagerStatus = 'daemon_not_running';
                        
                        foreach ($configuredProcesses as $configuredProcess) {
                            $processes[] = [
                                'name' => $configuredProcess['name'],
                                'status' => 'UNKNOWN',
                                'pid' => '-',
                                'uptime' => '-',
                                'start_time' => '-',
                                'description' => $configuredProcess['description'],
                                'command' => $configuredProcess['command']
                            ];
                        }
                    }
                } else {
                    // Supervisor not installed
                    $processManagerStatus = 'not_installed';
                    
                    foreach ($configuredProcesses as $configuredProcess) {
                        $processes[] = [
                            'name' => $configuredProcess['name'],
                            'status' => 'NOT INSTALLED',
                            'pid' => '-',
                            'uptime' => '-',
                            'start_time' => '-',
                            'description' => $configuredProcess['description'],
                            'command' => $configuredProcess['command']
                        ];
                    }
                }
            } elseif ($osInfo['is_macos']) {
                // macOS - Could use launchd, but typically supervisor for dev
                $processManager = 'none';
                $processManagerStatus = 'macos_not_configured';
                
                foreach ($configuredProcesses as $configuredProcess) {
                    $processes[] = [
                        'name' => $configuredProcess['name'],
                        'status' => 'N/A',
                        'pid' => '-',
                        'uptime' => '-',
                        'start_time' => '-',
                        'description' => $configuredProcess['description'],
                        'command' => $configuredProcess['command']
                    ];
                }
            } else {
                // Unknown OS
                $processManager = 'unknown';
                $processManagerStatus = 'unknown_os';
                
                foreach ($configuredProcesses as $configuredProcess) {
                    $processes[] = [
                        'name' => $configuredProcess['name'],
                        'status' => 'UNKNOWN',
                        'pid' => '-',
                        'uptime' => '-',
                        'start_time' => '-',
                        'description' => $configuredProcess['description'],
                        'command' => $configuredProcess['command']
                    ];
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to get supervisor status: ' . $e->getMessage());
            
            // On error, still show configured processes
            foreach ($configuredProcesses as $configuredProcess) {
                $processes[] = [
                    'name' => $configuredProcess['name'],
                    'status' => 'ERROR',
                    'pid' => '-',
                    'uptime' => '-',
                    'start_time' => '-',
                    'description' => $configuredProcess['description'],
                    'command' => $configuredProcess['command'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'processes' => $processes,
            'supervisor_available' => $supervisorAvailable,
            'process_manager' => $processManager,
            'process_manager_status' => $processManagerStatus,
            'os' => $osInfo
        ]);
    }

    /**
     * Get process description from configured list
     */
    private function getProcessDescription(string $processName, array $configuredProcesses): string
    {
        foreach ($configuredProcesses as $process) {
            if ($process['name'] === $processName) {
                return $process['description'];
            }
        }
        return '';
    }

    /**
     * Get process command from configured list
     */
    private function getProcessCommand(string $processName, array $configuredProcesses): string
    {
        foreach ($configuredProcesses as $process) {
            if ($process['name'] === $processName) {
                return $process['command'];
            }
        }
        return '';
    }
    
    /**
     * Get cron jobs status with date-wise logs
     */
    public function getCronStatus()
    {
        $tasks = [];
        
        // Define your cron tasks - Only active crons from Kernel.php
        $cronTasks = [
            [
                'name' => 'Process Scheduled SMS',
                'command' => 'sms:process-scheduled',
                'schedule' => 'Every minute',
                'frequency' => '*/1 * * * *'
            ],
            [
                'name' => 'Requeue Failed SMS',
                'command' => 'sms:requeue-failed',
                'schedule' => 'Daily (23:00)',
                'frequency' => '00 23 * * *'
            ],
            [
                'name' => 'Send Scheduled Emails',
                'command' => 'emails:send-schedule',
                'schedule' => 'Every minute',
                'frequency' => '*/1 * * * *'
            ],
            [
                'name' => 'SMS Update Pricing',
                'command' => 'sms:update-pricing',
                'schedule' => 'Daily (00:05)',
                'frequency' => '05 00 * * *'
            ],
            [
                'name' => 'Wallet Send Reminders',
                'command' => 'wallet:send-reminders',
                'schedule' => 'Daily (06:15)',
                'frequency' => '15 06 * * *'
            ],
            [
                'name' => 'Delivery Receipt Push',
                'command' => 'delivery-receipt:push',
                'schedule' => 'Every 5 minutes',
                'frequency' => '*/5 * * * *'
            ],
            [
                'name' => 'Virtual Numbers Sync',
                'command' => 'virtualnumbers:sync',
                'schedule' => 'Daily (02:30)',
                'frequency' => '30 02 * * *'
            ],
            [
                'name' => 'Fetch Nexmo Delivery Reports',
                'command' => 'nexmo:fetch-delivery-reports',
                'schedule' => 'Every 5 minutes',
                'frequency' => '*/5 * * * *'
            ],
            [
                'name' => 'Database Tidy',
                'command' => 'db:tidy',
                'schedule' => 'Daily (02:30)',
                'frequency' => '30 02 * * *'
            ],
            [
                'name' => 'XML to SMS Gateway',
                'command' => 'sms:xml-gateway',
                'schedule' => 'Every minute',
                'frequency' => '*/1 * * * *'
            ],
            [
                'name' => 'Daily Stats Report',
                'command' => 'report:daily-stats',
                'schedule' => 'Daily (06:00)',
                'frequency' => '00 06 * * *'
            ],
            [
                'name' => 'Virtual Number Expiry Report',
                'command' => 'report:virtual-number-expiry',
                'schedule' => 'Monday (06:00)',
                'frequency' => '00 06 * * 1'
            ],
            [
                'name' => 'Funds Alert Check',
                'command' => 'alert:funds-check',
                'schedule' => 'Twice Daily + Hourly',
                'frequency' => '00 06,17 * * * + hourly'
            ],
            [
                'name' => 'SMPP Regular Checks',
                'command' => 'smpp:regular-checks',
                'schedule' => 'Hourly (6AM-9PM)',
                'frequency' => '00 06-21 * * *'
            ],
            [
                'name' => 'SMS Heartbeat',
                'command' => 'sms:heartbeat',
                'schedule' => 'Every minute',
                'frequency' => '* * * * *'
            ],
            [
                'name' => 'Pooled Virtuals Monitor',
                'command' => 'pooledvirts:monitor',
                'schedule' => 'Every minute',
                'frequency' => '* * * * *'
            ],
            [
                'name' => 'URL Forward Daemon',
                'command' => 'urlforward:process',
                'schedule' => 'Every minute',
                'frequency' => '* * * * *'
            ],
            [
                'name' => 'Process Scheduled Notifications',
                'command' => 'notifications:process-scheduled',
                'schedule' => 'Every minute',
                'frequency' => '* * * * *'
            ],
             [
                'name' => 'Process Campaign Report',
                'command' => 'campaign:report',
                'schedule' => 'Hourly (5:00 AM - 9:00 PM)',
                'frequency' => '0 5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21 * * *'
            ],
        ];
        
        foreach ($cronTasks as $task) {
            $taskStatus = $this->getCronTaskStatus($task['command']);
            
            // Get logs count for last 7 days
            $logsCount = CronJobLog::where('command', $task['command'])
                ->where('started_at', '>=', now()->subDays(7))
                ->count();
            
            $tasks[] = [
                'name' => $task['name'],
                'command' => $task['command'],
                'schedule' => $task['schedule'],
                'last_run' => $taskStatus['last_run'],
                'next_run' => $taskStatus['next_run'],
                'status' => $taskStatus['status'],
                'duration' => $taskStatus['duration'],
                'logs_count_7days' => $logsCount
            ];
        }
        
        return response()->json([
            'success' => true,
            'tasks' => $tasks
        ]);
    }
    
    /**
     * Get individual cron task status
     */
    private function getCronTaskStatus($command)
    {
        // Get the latest log from database
        $lastLog = CronJobLog::getLatestForCommand($command);
        
        $status = 'pending';
        $lastRunTime = null;
        $duration = null;
        
        if ($lastLog) {
            $lastRunTime = $lastLog->started_at ? 
                $lastLog->started_at->format('Y-m-d H:i:s') : null;
            $status = $lastLog->status;
            $duration = $lastLog->formatted_duration;
        }
        
        // Calculate next run
        $nextRun = $this->calculateNextRun($command, $lastLog);
        
        return [
            'last_run' => $lastRunTime,
            'next_run' => $nextRun,
            'status' => $status,
            'duration' => $duration
        ];
    }
    
    /**
     * Calculate next run time
     */
    private function calculateNextRun($command, $lastLog = null)
    {
        $now = Carbon::now();
        
        // Pattern matching for common schedules
        if (strpos($command, 'update-pricing') !== false) {
            // Daily at 00:05
            $nextRun = $now->copy()->setTime(0, 5, 0);
            if ($now->hour >= 0 && $now->minute >= 5) {
                $nextRun->addDay();
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'db:tidy') !== false) {
            // Daily at 02:30
            $nextRun = $now->copy()->setTime(2, 30, 0);
            if ($now->hour >= 2 && $now->minute >= 30) {
                $nextRun->addDay();
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'send-reminders') !== false) {
            // Daily at 06:15
            $nextRun = $now->copy()->setTime(6, 15, 0);
            if ($now->hour >= 6 && $now->minute >= 15) {
                $nextRun->addDay();
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'virtualnumbers:sync') !== false) {
            // Daily at 02:30
            $nextRun = $now->copy()->setTime(2, 30, 0);
            if ($now->hour >= 2 && $now->minute >= 30) {
                $nextRun->addDay();
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'report:daily-stats') !== false) {
            // Daily at 06:00
            $nextRun = $now->copy()->setTime(6, 0, 0);
            if ($now->hour >= 6) {
                $nextRun->addDay();
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'report:virtual-number-expiry') !== false) {
            // Monday at 06:00
            $nextRun = $now->copy()->next(Carbon::MONDAY)->setTime(6, 0, 0);
            if ($now->dayOfWeek === Carbon::MONDAY && $now->hour < 6) {
                $nextRun = $now->copy()->setTime(6, 0, 0);
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'alert:funds-check') !== false) {
            // Twice daily at 6:00 and 17:00, plus hourly
            $hour = $now->hour;
            if ($hour < 6) {
                $nextRun = $now->copy()->setTime(6, 0, 0);
            } elseif ($hour < 17) {
                $nextRun = $now->copy()->setTime(17, 0, 0);
            } else {
                $nextRun = $now->copy()->addDay()->setTime(6, 0, 0);
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'smpp:regular-checks') !== false) {
            // Hourly from 6 AM to 9 PM
            $hour = $now->hour;
            if ($hour < 6) {
                $nextRun = $now->copy()->setTime(6, 0, 0);
            } elseif ($hour >= 21) {
                $nextRun = $now->copy()->addDay()->setTime(6, 0, 0);
            } else {
                $nextRun = $now->copy()->addHour()->startOfHour();
            }
            return $nextRun->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'sms:heartbeat') !== false) {
            // Every minute
            return $now->copy()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'pooledvirts:monitor') !== false) {
            // Every minute
            return $now->copy()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'urlforward:process') !== false) {
            // Every minute
            return $now->copy()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'notifications:process-scheduled') !== false) {
            // Every minute
            return $now->copy()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'delivery-receipt:push') !== false || 
                  strpos($command, 'nexmo:fetch-delivery-reports') !== false) {
            // Every 5 minutes
            $minutes = $now->minute;
            $nextMinute = (floor($minutes / 5) + 1) * 5;
            if ($nextMinute >= 60) {
                return $now->copy()->addHour()->startOfHour()->format('Y-m-d H:i:s');
            }
            return $now->copy()->minute($nextMinute)->second(0)->format('Y-m-d H:i:s');
        } elseif (strpos($command, 'process-scheduled') !== false ||
                  strpos($command, 'emails:send-schedule') !== false ||
                  strpos($command, 'sms:xml-gateway') !== false) {
            // Every minute
            return $now->copy()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');
        }
        
        return '-';
    }
    
    /**
     * Control supervisor processes
     */
    public function controlProcess(Request $request)
    {
        $request->validate([
            'process' => 'required|string',
            'action' => 'required|in:start,stop,restart'
        ]);
        
        $process = $request->input('process');
        $action = $request->input('action');
        
        try {
            $command = "supervisorctl {$action} {$process}";
            $output = shell_exec($command . ' 2>&1');
            
            // Clear cache if stopping
            if ($action === 'stop') {
                Cache::forget('supervisor_start_' . $process);
            }
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($action) . ' command sent to ' . $process,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to control process: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Run a cron job manually
     */
    public function runCronJob(Request $request)
    {
        $request->validate([
            'command' => 'required|string',
            'background' => 'nullable|boolean'
        ]);
        
        $command = $request->input('command');
        $runInBackground = $request->input('background', true); // Default to background execution
        
        // Whitelist of allowed commands for security - Only active crons
        $allowedCommands = [
            'sms:process-scheduled',
            'emails:send-schedule',
            'sms:update-pricing',
            'wallet:send-reminders',
            'delivery-receipt:push',
            'virtualnumbers:sync',
            'nexmo:fetch-delivery-reports',
            'db:tidy',
            'sms:xml-gateway',
            'report:daily-stats',
            'report:virtual-number-expiry',
            'alert:funds-check',
            'smpp:regular-checks',
            'sms:heartbeat',
            'pooledvirts:monitor',
            'urlforward:process',
            'notifications:process-scheduled',
        ];
        
        // Check if command is allowed
        if (!in_array($command, $allowedCommands)) {
            return response()->json([
                'success' => false,
                'message' => 'Command not allowed: ' . $command
            ], 403);
        }
        
        // Log the execution attempt
        \Log::info("Manual cron execution started: {$command}", [
            'command' => $command,
            'background' => $runInBackground,
            'user' => auth()->user()->username ?? 'unknown'
        ]);
        
        try {
            if ($runInBackground) {
                // Run command in background (non-blocking)
                return $this->runCommandInBackground($command);
            } else {
                // Run command synchronously (blocking) - may timeout for long commands
                return $this->runCommandSynchronously($command);
            }
        } catch (\Exception $e) {
            \Log::error("Manual cron execution failed: {$command}", [
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute command',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Run command in background (non-blocking)
     */
    private function runCommandInBackground(string $command)
    {
        $artisanPath = base_path('artisan');
        $phpBinary = PHP_BINARY;
        
        // Use date-wise log path
        $logFile = $this->getDateWiseLogPath('manual-cron.log');
        
        // Build the full command
        $fullCommand = sprintf(
            '%s %s %s >> %s 2>&1 &',
            escapeshellarg($phpBinary),
            escapeshellarg($artisanPath),
            $command,
            escapeshellarg($logFile)
        );
        
        // For Windows, use different approach
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $fullCommand = sprintf(
                'start /B %s %s %s >> %s 2>&1',
                escapeshellarg($phpBinary),
                escapeshellarg($artisanPath),
                $command,
                escapeshellarg($logFile)
            );
            pclose(popen($fullCommand, 'r'));
        } else {
            // Unix/Linux
            exec($fullCommand);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Command started in background',
            'command' => $command,
            'mode' => 'background',
            'log_file' => $logFile,
            'note' => 'Check the cron logs or log file for execution results'
        ]);
    }
    
    /**
     * Run command synchronously (blocking)
     */
    private function runCommandSynchronously(string $command)
    {
        // Increase execution time for long-running commands
        set_time_limit(300); // 5 minutes
        
        $startTime = microtime(true);
        
        // Run the artisan command
        $exitCode = \Artisan::call($command);
        $output = \Artisan::output();
        
        $duration = round(microtime(true) - $startTime, 2);
        
        // Log the execution
        \Log::info("Manual cron execution completed: {$command}", [
            'command' => $command,
            'exit_code' => $exitCode,
            'duration' => $duration,
            'user' => auth()->user()->username ?? 'unknown'
        ]);
        
        if ($exitCode === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Command executed successfully',
                'command' => $command,
                'exit_code' => $exitCode,
                'duration' => $duration . 's',
                'mode' => 'synchronous',
                'output' => trim($output)
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Command completed with errors',
                'command' => $command,
                'exit_code' => $exitCode,
                'duration' => $duration . 's',
                'mode' => 'synchronous',
                'error' => trim($output)
            ]);
        }
    }
    
    /**
     * Get process log file content
     */
    public function getProcessLog(Request $request)
    {
        $processName = $request->input('process');
        
        if (!$processName) {
            return response()->json([
                'success' => false,
                'message' => 'Process name is required'
            ], 400);
        }
        
        // Map process names to actual log file names (matching supervisor config)
        $logFileMapping = [
            'sms_process_queue' => 'sms_process_queue',
            'smpp_dlr_receiver' => 'smpp_dlr_receiver',
            'rabbitmq_consume_emails' => 'rabbitmq_consume_emails',
            'smpp_monitor' => 'smpp_monitor',
            'queue_inbound_sms' => 'queue_inbound-sms',
            'nexmo_delivery_queue' => 'nexmo_delivery_queue',
            'campaign_update' => 'campaign_update',
        ];
        
        // Get the actual log file name
        $logFileName = $logFileMapping[$processName] ?? $processName;
        
        // Get today's date for dated log folders
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Try multiple possible log paths - PRIORITIZE dated folders!
        // Using dynamic base_path() for cross-environment compatibility
        $possiblePaths = [
            // Today's dated logs (HIGHEST PRIORITY)
            storage_path("logs/{$today}/{$logFileName}.log"),
            base_path("storage/logs/{$today}/{$logFileName}.log"),
            
            // Yesterday's dated logs
            storage_path("logs/{$yesterday}/{$logFileName}.log"),
            base_path("storage/logs/{$yesterday}/{$logFileName}.log"),
            
            // Direct logs (LOWEST PRIORITY - only as fallback)
            storage_path("logs/{$logFileName}.log"),
            base_path("storage/logs/{$logFileName}.log"),
        ];
        
        // Find the first existing log file (dated folders are checked first)
        $logPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $logPath = $path;
                break;
            }
        }
        
        try {
            if (!$logPath) {
                // Try to get the path from supervisor config if available
                // Map process name to supervisor process name
                $supervisorProcessMapping = [
                    'inbound_sms_queue' => 'inbound-sms-worker',
                ];
                $supervisorProcessName = $supervisorProcessMapping[$processName] ?? $processName;
                $supervisorOutput = shell_exec("supervisorctl status {$supervisorProcessName} 2>&1");
                
                return response()->json([
                    'success' => true,
                    'log' => "Log file not found. Tried paths:\n" . implode("\n", $possiblePaths) . "\n\nNote: Logs may be in dated folders (e.g., logs/" . $today . "/)\n\nSupervisor status:\n" . $supervisorOutput
                ]);
            }
            
            // Check file size to avoid reading huge files
            $fileSize = filesize($logPath);
            if ($fileSize > 10485760) { // 10MB
                // For large files, only read the last 5000 lines
                $lines = [];
                $file = new \SplFileObject($logPath, 'r');
                $file->seek(PHP_INT_MAX);
                $lastLine = $file->key();
                $startLine = max(0, $lastLine - 5000);
                
                $file->seek($startLine);
                while (!$file->eof()) {
                    $line = $file->fgets();
                    if ($line) {
                        $lines[] = $line;
                    }
                }
                $logContent = implode('', $lines);
            } else {
                // For smaller files, read everything
                $logContent = file_get_contents($logPath);
            }
            
            return response()->json([
                'success' => true,
                'log' => $logContent ?: 'Log file is empty',
                'path' => $logPath,
                'size' => round($fileSize / 1024, 2) . ' KB'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to read log file: ' . $e->getMessage(),
                'tried_paths' => $possiblePaths
            ], 500);
        }
    }

    /**
     * Get date-wise summary of cron logs for a specific command
     */
    public function getCronLogsSummary(Request $request)
    {
        $command = $request->input('command');
        $days = $request->input('days', 30);

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Command is required'
            ], 400);
        }

        $endDate = now();
        $startDate = now()->subDays($days);

        $summary = CronJobLog::where('command', $command)
            ->whereBetween('started_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(started_at) as log_date'))
            ->selectRaw('COUNT(*) as total_logs')
            ->selectRaw('SUM(CASE WHEN status = "success" THEN 1 ELSE 0 END) as success_count')
            ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_count')
            ->selectRaw('SUM(CASE WHEN status = "running" THEN 1 ELSE 0 END) as running_count')
            ->selectRaw('AVG(duration) as avg_duration')
            ->groupBy('log_date')
            ->orderBy('log_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'command' => $command,
            'data' => $summary,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Get cron logs for a specific command and date
     */
    public function getCronLogs(Request $request)
    {
        $command = $request->input('command');
        $date = $request->input('date', date('Y-m-d'));
        
        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Command is required'
            ], 400);
        }
        
        // Get logs for the specific command and date
        $logs = CronJobLog::where('command', $command)
            ->whereDate('started_at', $date)
            ->orderBy('started_at', 'desc')
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'started_at' => $log->started_at->format('Y-m-d H:i:s'),
                    'finished_at' => $log->finished_at ? $log->finished_at->format('Y-m-d H:i:s') : '-',
                    'duration' => $log->formatted_duration,
                    'status' => $log->status,
                    'has_log_file' => $log->hasLogFile(),
                    'log_file' => $log->log_file,
                    'output_preview' => $log->output ? substr($log->output, 0, 200) . (strlen($log->output) > 200 ? '...' : '') : null,
                    'has_error' => !empty($log->error)
                ];
            });
        
        return response()->json([
            'success' => true,
            'command' => $command,
            'date' => $date,
            'logs' => $logs,
            'total' => $logs->count()
        ]);
    }

    /**
     * Download cron logs as CSV
     */
    public function downloadCronLogs(Request $request)
    {
        $command = $request->input('command');
        $date = $request->input('date');
        $format = $request->input('format', 'csv');

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Command is required'
            ], 400);
        }

        $query = CronJobLog::where('command', $command);

        if ($date) {
            $query->whereDate('started_at', $date);
        }

        $logs = $query->orderBy('started_at', 'desc')->get();

        if ($format === 'json') {
            return $this->downloadAsJson($logs, $command, $date);
        }

        return $this->downloadAsCsv($logs, $command, $date);
    }

    /**
     * Download logs as CSV
     */
    private function downloadAsCsv($logs, $command, $date = null)
    {
        $filename = 'cron_logs_' . str_replace(':', '_', $command);
        if ($date) {
            $filename .= '_' . $date;
        }
        $filename .= '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // CSV Header
            fputcsv($file, [
                'ID',
                'Command',
                'Status',
                'Started At',
                'Finished At',
                'Duration (seconds)',
                'Output',
                'Error',
                'Log File'
            ]);

            // CSV Data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->command,
                    $log->status,
                    $log->started_at ? $log->started_at->format('Y-m-d H:i:s') : '',
                    $log->finished_at ? $log->finished_at->format('Y-m-d H:i:s') : '',
                    $log->duration ?? '',
                    $log->output ?? '',
                    $log->error ?? '',
                    $log->log_file ?? ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download logs as JSON
     */
    private function downloadAsJson($logs, $command, $date = null)
    {
        $filename = 'cron_logs_' . str_replace(':', '_', $command);
        if ($date) {
            $filename .= '_' . $date;
        }
        $filename .= '_' . date('Ymd_His') . '.json';

        $data = $logs->map(function($log) {
            return [
                'id' => $log->id,
                'command' => $log->command,
                'status' => $log->status,
                'started_at' => $log->started_at ? $log->started_at->format('Y-m-d H:i:s') : null,
                'finished_at' => $log->finished_at ? $log->finished_at->format('Y-m-d H:i:s') : null,
                'duration' => $log->duration,
                'formatted_duration' => $log->formatted_duration,
                'output' => $log->output,
                'error' => $log->error,
                'log_file' => $log->log_file
            ];
        });

        return response()->json($data, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get single cron log details
     */
    public function getCronLogDetail(Request $request, $id)
    {
        $log = CronJobLog::find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log not found'
            ], 404);
        }

        // Find the actual log file path
        $logFilePath = $log->findLogFile();

        return response()->json([
            'success' => true,
            'log' => [
                'id' => $log->id,
                'command' => $log->command,
                'status' => $log->status,
                'started_at' => $log->started_at ? $log->started_at->format('Y-m-d H:i:s') : null,
                'finished_at' => $log->finished_at ? $log->finished_at->format('Y-m-d H:i:s') : null,
                'duration' => $log->duration,
                'formatted_duration' => $log->formatted_duration,
                'output' => $log->output,
                'error' => $log->error,
                'log_file' => $logFilePath,
                'log_file_exists' => $logFilePath !== null,
                'log_file_content' => $log->getLogContent()
            ]
        ]);
    }

    /**
     * Download cron log file
     */
    public function downloadCronLogFile(Request $request, $id)
    {
        $log = CronJobLog::find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log not found'
            ], 404);
        }

        $logFile = $log->findLogFile();

        if (!$logFile || !file_exists($logFile)) {
            return response()->json([
                'success' => false,
                'message' => 'Log file not found'
            ], 404);
        }

        $filename = $log->command . '_' . ($log->started_at ? $log->started_at->format('Y-m-d_His') : 'unknown') . '.log';
        $filename = str_replace(':', '_', $filename);

        return response()->download($logFile, $filename);
    }

    /**
     * Get list of all cron jobs with enable/disable status
     */
    public function getCronList()
    {
        try {
            // Get all cron job settings from database
            $cronSettings = \App\Models\CronJobSetting::orderBy('name')->get();
            
            $crons = $cronSettings->map(function($cron) {
                return [
                    'id' => $cron->id,
                    'name' => $cron->name,
                    'command' => $cron->command,
                    'schedule' => $cron->schedule,
                    'description' => $cron->description ?? '',
                    'is_enabled' => $cron->enabled,
                    'last_toggled_at' => $cron->last_toggled_at ? $cron->last_toggled_at->format('Y-m-d H:i:s') : null,
                    'toggled_by' => $cron->toggled_by
                ];
            });
            
            return response()->json([
                'success' => true,
                'crons' => $crons,
                'total' => $crons->count(),
                'enabled_count' => $crons->where('is_enabled', true)->count(),
                'disabled_count' => $crons->where('is_enabled', false)->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to get cron list: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load cron list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle cron job by ID
     */
    public function toggleCronById(Request $request)
    {
        $request->validate([
            'cron_id' => 'required|integer',
            'is_enabled' => 'required|boolean'
        ]);

        $cronId = $request->input('cron_id');
        $isEnabled = $request->input('is_enabled');
        $user = auth()->user();
        $toggledBy = $user ? ($user->username ?? $user->email ?? 'Unknown') : 'System';

        try {
            $cron = \App\Models\CronJobSetting::find($cronId);
            
            if (!$cron) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cron job not found'
                ], 404);
            }
            
            $cron->update([
                'enabled' => $isEnabled,
                'last_toggled_at' => now(),
                'toggled_by' => $toggledBy,
            ]);
            
            // Clear cache
            \Illuminate\Support\Facades\Cache::forget('cron_enabled_' . md5($cron->command));
            \Illuminate\Support\Facades\Cache::forget('cron_settings_all');
            
            \Log::info('Cron job toggled by ID', [
                'cron_id' => $cronId,
                'command' => $cron->command,
                'enabled' => $isEnabled,
                'toggled_by' => $toggledBy
            ]);

            return response()->json([
                'success' => true,
                'enabled' => $isEnabled,
                'message' => $isEnabled ? 'Cron job enabled successfully' : 'Cron job disabled successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to toggle cron job by ID', [
                'cron_id' => $cronId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle all cron jobs
     */
    public function toggleAllCrons(Request $request)
    {
        $request->validate([
            'is_enabled' => 'required|boolean'
        ]);

        $isEnabled = $request->input('is_enabled');
        $user = auth()->user();
        $toggledBy = $user ? ($user->username ?? $user->email ?? 'Unknown') : 'System';

        try {
            // Update all cron jobs
            $updated = \App\Models\CronJobSetting::query()->update([
                'enabled' => $isEnabled,
                'last_toggled_at' => now(),
                'toggled_by' => $toggledBy,
            ]);
            
            // Clear all cron caches
            $crons = \App\Models\CronJobSetting::all();
            foreach ($crons as $cron) {
                \Illuminate\Support\Facades\Cache::forget('cron_enabled_' . md5($cron->command));
            }
            \Illuminate\Support\Facades\Cache::forget('cron_settings_all');
            
            \Log::info('All cron jobs toggled', [
                'enabled' => $isEnabled,
                'count' => $updated,
                'toggled_by' => $toggledBy
            ]);

            return response()->json([
                'success' => true,
                'enabled' => $isEnabled,
                'updated_count' => $updated,
                'message' => $isEnabled ? 'All cron jobs enabled successfully' : 'All cron jobs disabled successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to toggle all cron jobs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle all cron jobs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cron job settings (enabled/disabled status)
     */
    public function getCronSettings()
    {
        try {
            $settings = \App\Models\CronJobSetting::all()->keyBy('command');
            
            return response()->json([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load cron settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle cron job enabled/disabled state
     */
    public function toggleCron(Request $request)
    {
        $request->validate([
            'command' => 'required|string'
        ]);

        $command = $request->input('command');
        $user = auth()->user();
        $toggledBy = $user ? ($user->username ?? $user->email ?? 'Unknown') : 'System';

        try {
            $result = \App\Models\CronJobSetting::toggle($command, $toggledBy);

            if ($result['success']) {
                \Log::info('Cron job toggled', [
                    'command' => $command,
                    'enabled' => $result['enabled'],
                    'toggled_by' => $toggledBy
                ]);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Failed to toggle cron job', [
                'command' => $command,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable a cron job
     */
    public function enableCron(Request $request)
    {
        $request->validate([
            'command' => 'required|string'
        ]);

        $command = $request->input('command');
        $user = auth()->user();
        $toggledBy = $user ? ($user->username ?? $user->email ?? 'Unknown') : 'System';

        try {
            $result = \App\Models\CronJobSetting::enable($command, $toggledBy);

            if ($result) {
                \Log::info('Cron job enabled', [
                    'command' => $command,
                    'enabled_by' => $toggledBy
                ]);

                return response()->json([
                    'success' => true,
                    'enabled' => true,
                    'message' => 'Cron job enabled successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Cron job not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enable cron job: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable a cron job
     */
    public function disableCron(Request $request)
    {
        $request->validate([
            'command' => 'required|string'
        ]);

        $command = $request->input('command');
        $user = auth()->user();
        $toggledBy = $user ? ($user->username ?? $user->email ?? 'Unknown') : 'System';

        try {
            $result = \App\Models\CronJobSetting::disable($command, $toggledBy);

            if ($result) {
                \Log::info('Cron job disabled', [
                    'command' => $command,
                    'disabled_by' => $toggledBy
                ]);

                return response()->json([
                    'success' => true,
                    'enabled' => false,
                    'message' => 'Cron job disabled successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Cron job not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to disable cron job: ' . $e->getMessage()
            ], 500);
        }
    }
}
