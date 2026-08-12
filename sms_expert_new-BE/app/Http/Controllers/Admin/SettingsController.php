<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Admin\Traits\LegacyCustomerList;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;
use App\Models\User;
use App\Models\ServerSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class SettingsController extends Controller
{
    use LegacyCustomerList;

    protected $logController;

    public function __construct()
    {
        $this->logController = new LogController();
    }

    /**
     * Canonical list of RabbitMQ queues (topics) shown on the admin "Queues" tab.
     * label => queue name (env-overridable, matching RabbitMQService::declareQueues).
     */
    private function monitoredQueues(): array
    {
        return [
            'SMS Outbound'           => env('RABBITMQ_SMS_QUEUE', 'sms.outbound'),
            'SMS Priority'           => env('RABBITMQ_PRIORITY_QUEUE', 'sms.priority'),
            'SMS Failed / Retry'     => env('RABBITMQ_FAILED_QUEUE', 'sms.failed'),
            'SMS Dead Letter'        => 'sms.dead',
            'DLR (delivery receipts)'=> env('RABBITMQ_DLR_QUEUE', 'sms.dlr'),
            'Inbound SMS'            => env('RABBITMQ_INBOUND_QUEUE', 'sms.inbound'),
            'Campaign Processing'    => env('RABBITMQ_CAMPAIGN_QUEUE', 'campaign.process'),
            'DLR Callback Push'      => env('RABBITMQ_DLR_CALLBACK_QUEUE', 'dlr.callback.push'),
            'Email Notifications'    => 'email.notifications',
            'Nexmo Delivery Reports' => env('RABBITMQ_NEXMO_DELIVERY_QUEUE', 'nexmo.delivery.reports'),
            'Push Notifications'     => env('RABBITMQ_PUSH_QUEUE', 'push.notifications'),
            'Webhook DLR'            => env('RABBITMQ_WEBHOOK_DLR_QUEUE', 'webhook.dlr'),
            'Webhook Inbound'        => env('RABBITMQ_WEBHOOK_INBOUND_QUEUE', 'webhook.inbound'),
        ];
    }

    /**
     * AJAX: per-topic pending message counts for the admin "Queues" tab.
     * Kept off the page-load path (queried on demand) so a slow/unreachable
     * RabbitMQ never blocks the Settings page.
     */
    public function queueStatus()
    {
        try {
            $rabbit = new \App\Services\Queue\RabbitMQService();
            $queues = $rabbit->getQueuesStatus($this->monitoredQueues());

            return response()->json([
                'success'       => true,
                'queues'        => $queues,
                'total_pending' => array_sum(array_column($queues, 'messages')),
                'checked_at'    => Carbon::now('Europe/London')->format('D, jS M Y H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Queue status check failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not reach RabbitMQ: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * AJAX: browse the actual messages currently in a queue (read-only — messages
     * are requeued, not consumed). Restricted to the known queue list so an
     * arbitrary queue name can't be passed in.
     */
    public function queueMessages(Request $request)
    {
        $queue = (string) $request->query('queue', '');
        $limit = (int) $request->query('limit', 20);

        if (!in_array($queue, array_values($this->monitoredQueues()), true)) {
            return response()->json(['success' => false, 'message' => 'Unknown queue.']);
        }

        try {
            $rabbit = new \App\Services\Queue\RabbitMQService();
            $messages = $rabbit->browseQueueMessages($queue, $limit);

            return response()->json([
                'success'    => true,
                'queue'      => $queue,
                'count'      => count($messages),
                'messages'   => $messages,
                'checked_at' => Carbon::now('Europe/London')->format('D, jS M Y H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Queue browse failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not read queue: ' . $e->getMessage()]);
        }
    }

    /**
     * Dedicated full-page view of a single queue's messages (opened from the
     * "View" link on the Queues tab). Messages themselves are loaded client-side
     * from queueMessages() so a slow RabbitMQ never blocks the page render.
     */
    public function queueView(Request $request)
    {
        $queue = (string) $request->query('queue', '');
        $label = array_search($queue, $this->monitoredQueues(), true);

        if ($label === false) {
            abort(404, 'Unknown queue');
        }

        return view('admin.settings.queue-view', [
            'queue' => $queue,
            'label' => $label,
        ]);
    }

    /**
     * Display the settings page with tabs
     */
    public function index(Request $request)
    {
        $activeTab = $request->get('tab', 'logs');
        
        // Get log data for logs tab
        $logData = $this->getLogData($request);
        
        // Get environment variables grouped by sections for general tab
        $envSections = $this->getEnvVariablesBySection();
        
        // Get customer settings data
        $customerSettingsData = $this->getCustomerSettingsData();
        
        // Get system status data
        $systemStatusData = $this->getSystemStatusData();

        // Get server settings for server settings tab
        $oldServer = ServerSetting::getOldServer() ?? new ServerSetting(['server_type' => 'old_server']);
        $newServer = ServerSetting::getNewServer() ?? new ServerSetting(['server_type' => 'new_server']);

        return view('admin.settings.index', array_merge($logData, $customerSettingsData, $systemStatusData, [
            'activeTab' => $activeTab,
            'envSections' => $envSections,
            'oldServer' => $oldServer,
            'newServer' => $newServer,
        ]));
    }

    /**
     * Run a READ-ONLY SQL query from the Settings > Query tab and return rows as JSON.
     *
     * Hard safety rules (server-side — never trusts the UI):
     *  - Super-admin only (or can_run_queries permission).
     *  - SELECT / SHOW / DESCRIBE / EXPLAIN / WITH only; single statement; no
     *    INSERT/UPDATE/DELETE/DDL/file/exec keywords; comments stripped first so
     *    nothing can be hidden.
     *  - Result set hard-capped at 1000 rows (SELECT/WITH are wrapped in a derived
     *    table with LIMIT so a huge table can't be dumped).
     *  - Every run is written to the audit log.
     */
    public function runQuery(Request $request)
    {
        $adminUser = Session::get('admin_user');
        $isSuperAdmin = isset($adminUser['role']) && $adminUser['role'] === 'super_admin';
        $canRun = $isSuperAdmin || (($adminUser['permissions']['can_run_queries'] ?? false));

        if (!$canRun) {
            return response()->json(['success' => false, 'message' => 'You are not authorised to run queries.'], 403);
        }

        $sql = trim((string) $request->input('query', ''));
        if ($sql === '') {
            return response()->json(['success' => false, 'message' => 'Query is empty.'], 422);
        }

        $check = $this->validateReadOnlyQuery($sql);
        if ($check !== true) {
            return response()->json(['success' => false, 'message' => $check], 422);
        }

        $maxRows = 1000;
        $execSql = $this->applyRowLimit($sql, $maxRows);

        try {
            $start = microtime(true);
            $result = DB::select($execSql);
            $ms = round((microtime(true) - $start) * 1000, 1);

            $rows = array_map(fn ($r) => (array) $r, $result);
            $columns = !empty($rows) ? array_keys($rows[0]) : [];

            Log::info('Admin raw query executed', [
                'admin'  => $adminUser['email'] ?? ($adminUser['uname'] ?? 'unknown'),
                'query'  => $sql,
                'rows'   => count($rows),
                'ip'     => $request->ip(),
            ]);

            return response()->json([
                'success'       => true,
                'columns'       => $columns,
                'rows'          => $rows,
                'row_count'     => count($rows),
                'execution_ms'  => $ms,
                'truncated'     => count($rows) >= $maxRows,
                'executed_sql'  => $execSql,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Query error: ' . $e->getMessage()], 422);
        }
    }

    /**
     * DLR Status Update — admin uploads a Vonage/Nexmo delivery-report CSV export.
     *
     * Mirrors the `dlr:import-csv` command: each row is turned into a DLR record and, by
     * default, QUEUED to RabbitMQ (nexmo.delivery.reports) so the running DLR consumer
     * (nexmo:process-delivery-queue) matches message_id -> smsg_log and updates
     * deliverystatus2 + fires the customer push callback. `--sync` equivalent processes inline.
     *
     * Gated by the auto-granted `can_manage_dlr_update` admin permission.
     */
    public function importDlrCsv(Request $request)
    {
        $adminUser = Session::get('admin_user');
        $isSuperAdmin = isset($adminUser['role']) && $adminUser['role'] === 'super_admin';
        $canManage = $isSuperAdmin || (($adminUser['permissions']['can_manage_dlr_update'] ?? false));

        if (!$canManage) {
            return redirect()->route('admin.settings', ['tab' => 'dlr-update'])
                ->with('dlr_error', 'You are not authorised to update DLR statuses.');
        }

        $request->validate([
            'dlr_file' => ['required', 'file', 'mimes:csv,txt', 'max:65536'], // 64 MB (match php.ini upload_max_filesize/post_max_size)
        ], [], ['dlr_file' => 'DLR CSV file']);

        $mode = $request->input('dlr_mode') === 'sync' ? 'sync' : 'queue';

        // Persist the upload into public/ using the same naming style as the exported files
        // (report_SMS_<hash>_<Ymd>.csv) so operators can locate what was processed.
        $stored = 'report_SMS_' . substr(md5(uniqid('', true)), 0, 8) . '_' . now()->format('Ymd') . '.csv';
        $request->file('dlr_file')->move(public_path(), $stored);
        $path = public_path($stored);

        $fh = @fopen($path, 'r');
        if ($fh === false) {
            return redirect()->route('admin.settings', ['tab' => 'dlr-update'])
                ->with('dlr_error', 'Could not open the uploaded file.');
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);
            return redirect()->route('admin.settings', ['tab' => 'dlr-update'])
                ->with('dlr_error', 'The uploaded CSV is empty.');
        }

        $map = array_flip(array_map(fn ($h) => strtolower(trim((string) $h)), $header));
        foreach (['message_id', 'status'] as $req) {
            if (!isset($map[$req])) {
                fclose($fh);
                return redirect()->route('admin.settings', ['tab' => 'dlr-update'])
                    ->with('dlr_error', "CSV is missing the required '{$req}' column. Found: " . implode(', ', $header));
            }
        }

        $col = fn (array $row, string $name) => isset($map[$name]) ? trim((string) ($row[$map[$name]] ?? '')) : '';

        $queueService = new \App\Services\Queue\NexmoDeliveryQueueService(app(\App\Services\Queue\RabbitMQService::class));

        $read = 0; $queued = 0; $skipped = 0; $batch = [];
        try {
            while (($row = fgetcsv($fh)) !== false) {
                $messageId = $col($row, 'message_id');
                if ($messageId === '' || strtolower($messageId) === 'message_id') {
                    continue; // blank line or a repeated header
                }

                $record = [
                    'message_id'             => $messageId,
                    'status'                 => $col($row, 'status'),
                    'error_code'             => $col($row, 'error_code'),
                    'error_code_description' => $col($row, 'error_code_description'),
                    'total_price'            => $col($row, 'total_price') ?: 0,
                    'to'                     => '', // CSV `to` is Excel-corrupted; message_id is the match key
                ];
                $read++;

                if ($mode === 'sync') {
                    $queueService->processDeliveryReport($record);
                    $queued++;
                } else {
                    $batch[] = $record;
                    if (count($batch) >= 500) {
                        $r = $queueService->queueBatchDeliveryReports($batch);
                        $queued  += $r['queued'];
                        $skipped += $r['failed'];
                        $batch = [];
                    }
                }
            }

            if ($mode === 'queue' && !empty($batch)) {
                $r = $queueService->queueBatchDeliveryReports($batch);
                $queued  += $r['queued'];
                $skipped += $r['failed'];
            }
        } catch (\Throwable $e) {
            fclose($fh);
            Log::error('DLR CSV import failed', ['file' => $stored, 'error' => $e->getMessage()]);
            return redirect()->route('admin.settings', ['tab' => 'dlr-update'])
                ->with('dlr_error', 'Import failed: ' . $e->getMessage());
        }
        fclose($fh);

        Log::info('DLR CSV imported via admin settings', [
            'admin'  => $adminUser['email'] ?? ($adminUser['username'] ?? 'unknown'),
            'file'   => $stored,
            'mode'   => $mode,
            'read'   => $read,
            'queued' => $queued,
            'ip'     => $request->ip(),
        ]);

        return redirect()->route('admin.settings', ['tab' => 'dlr-update'])
            ->with('dlr_result', [
                'file'    => $stored,
                'mode'    => $mode,
                'read'    => $read,
                'queued'  => $queued,
                'skipped' => $skipped,
            ]);
    }

    /**
     * Validate that a SQL string is a single, read-only statement.
     * Returns true if safe, or a string error message if not.
     */
    private function validateReadOnlyQuery(string $sql)
    {
        $clean = rtrim(trim($sql), ';');

        // Strip comments so a statement can't be hidden inside them.
        $stripped = preg_replace('/--[^\n]*/', ' ', $clean);       // -- line comment
        $stripped = preg_replace('/#[^\n]*/', ' ', $stripped);     // # line comment
        $stripped = preg_replace('!/\*.*?\*/!s', ' ', $stripped);  // /* block comment */
        $stripped = trim($stripped);

        if ($stripped === '') {
            return 'Query is empty after removing comments.';
        }

        // Single statement only — reject embedded semicolons.
        if (strpos($stripped, ';') !== false) {
            return 'Only a single statement is allowed (remove any semicolons).';
        }

        // Must begin with a read-only verb.
        if (!preg_match('/^\s*(select|show|describe|desc|explain|with)\b/i', $stripped)) {
            return 'Only SELECT / SHOW / DESCRIBE / EXPLAIN queries are allowed.';
        }

        // Reject any write / DDL / file / exec keyword anywhere.
        $forbidden = [
            'insert', 'update', 'delete', 'drop', 'alter', 'truncate', 'create',
            'replace', 'grant', 'revoke', 'rename', 'lock', 'unlock', 'call',
            'prepare', 'execute', 'flush', 'reset', 'shutdown', 'kill', 'handler',
        ];
        foreach ($forbidden as $kw) {
            if (preg_match('/\b' . $kw . '\b/i', $stripped)) {
                return "Query contains a disallowed keyword ('{$kw}'). SELECT-only queries are permitted.";
            }
        }

        // File-access / variable-write patterns (not always word-bounded).
        foreach (['outfile', 'dumpfile', 'load_file', 'load data', 'into outfile', 'into dumpfile'] as $needle) {
            if (stripos($stripped, $needle) !== false) {
                return 'Query contains a disallowed file/IO operation.';
            }
        }
        // Block "SET" / session-var writes but allow "OFFSET" (which contains "set").
        if (preg_match('/(^|\s)set\s/i', $stripped) || preg_match('/@@/', $stripped)) {
            return 'Setting variables is not allowed.';
        }

        return true;
    }

    /**
     * Cap the result set at $max rows. SELECT/WITH are wrapped in a derived table so the
     * limit always applies (even if the inner query has its own LIMIT). SHOW/DESCRIBE/
     * EXPLAIN are inherently bounded and run as-is.
     */
    private function applyRowLimit(string $sql, int $max): string
    {
        $clean = rtrim(trim($sql), ';');
        if (preg_match('/^\s*(select|with)\b/i', $clean)) {
            return "SELECT * FROM ({$clean}) AS _adminq LIMIT {$max}";
        }
        return $clean;
    }

    /**
     * Get all smsg_log tables from database
     */
    private function getSmsgLogTables()
    {
        $tables = DB::select("
            SELECT table_name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE table_name LIKE 'smsg_log%'
            AND TABLE_SCHEMA = DATABASE()
        ");

        return collect($tables)->map(function ($table) {
            return $table->table_name ?? $table->TABLE_NAME;
        })->toArray();
    }

    /**
     * Keep only the smsg_log_* archives whose YYMM month falls inside [$start, $end].
     * `timesent` is stored as YYYYMMDDHHMMSS, archives are named smsg_log_YYMM (e.g.
     * smsg_log_2604 = April 2026). The live `smsg_log` rolling table is always kept
     * because it contains the current writing month and can't be inferred from its name.
     *
     * Without this filter every COUNT fanned across ~14 tables (~100M rows scanned)
     * even for a 1-hour window where only the live table has rows.
     */
    private function filterTablesByRange(array $tables, string $start, string $end): array
    {
        $startYm = substr($start, 0, 6); // YYYYMM
        $endYm   = substr($end, 0, 6);

        return array_values(array_filter($tables, function ($t) use ($startYm, $endYm) {
            if ($t === 'smsg_log') {
                return true;
            }
            if (preg_match('/^smsg_log_(\d{2})(\d{2})$/', $t, $m)) {
                $tableYm = '20' . $m[1] . $m[2]; // smsg_log_2604 -> 202604
                return $tableYm >= $startYm && $tableYm <= $endYm;
            }
            // Unknown naming — keep, safer than dropping a real partition.
            return true;
        }));
    }

    /**
     * Build UNION ALL query for smsg_log tables with conditions
     */
    private function buildSmsgLogUnionQuery($selectFields, $conditions = [])
    {
        $tables = $this->getSmsgLogTables();
        
        if (empty($tables)) {
            return null;
        }
        
        $queries = collect($tables)->map(function ($tableName) use ($selectFields, $conditions) {
            $query = "SELECT {$selectFields} FROM {$tableName}";
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            
            return $query;
        });
        
        return implode(' UNION ALL ', $queries->toArray());
    }

    /**
     * Get system status data - SMS traffic statistics from all smsg_log tables
     */
    private function getSystemStatusData()
    {
        try {
            $now = Carbon::now();
            
            // Time ranges for queries
            $oneSecondAgo = $now->copy()->subSecond()->format('YmdHis');
            $oneMinuteAgo = $now->copy()->subMinute()->format('YmdHis');
            $oneHourAgo = $now->copy()->subHour()->format('YmdHis');
            $todayStart = $now->copy()->startOfDay()->format('YmdHis');
            $currentTime = $now->format('YmdHis');
            
            // Cache key for real-time stats (cache for 5 seconds)
            $cacheKey = 'sms_traffic_stats_' . floor(time() / 5);
            
            $stats = Cache::remember($cacheKey, 5, function () use ($oneSecondAgo, $oneMinuteAgo, $oneHourAgo, $todayStart, $currentTime) {

                // Get all smsg_log tables
                $tables = $this->getSmsgLogTables();

                if (empty($tables)) {
                    return $this->getEmptyStats();
                }

                // Only query archives whose month overlaps the window — without this
                // every COUNT scanned ~100M rows across 14 monthly archives.
                $tablesPerSecond = $this->filterTablesByRange($tables, $oneSecondAgo, $currentTime);
                $tablesPerMinute = $this->filterTablesByRange($tables, $oneMinuteAgo, $currentTime);
                $tablesPerHour   = $this->filterTablesByRange($tables, $oneHourAgo, $currentTime);
                $tablesToday     = $this->filterTablesByRange($tables, $todayStart, $currentTime);

                // SMS per second (last second)
                $smsPerSecond = $this->countFromAllTables($tablesPerSecond, [
                    "timesent >= '{$oneSecondAgo}'",
                    "timesent <= '{$currentTime}'"
                ]);

                // SMS per minute (last minute)
                $smsPerMinute = $this->countFromAllTables($tablesPerMinute, [
                    "timesent >= '{$oneMinuteAgo}'",
                    "timesent <= '{$currentTime}'"
                ]);

                // SMS per hour (last hour)
                $smsPerHour = $this->countFromAllTables($tablesPerHour, [
                    "timesent >= '{$oneHourAgo}'",
                    "timesent <= '{$currentTime}'"
                ]);

                // SMS today
                $smsToday = $this->countFromAllTables($tablesToday, [
                    "timesent >= '{$todayStart}'",
                    "timesent <= '{$currentTime}'"
                ]);

                // SMS by initiator (API vs Dashboard) - today
                $smsByInitiator = $this->groupByFromAllTables($tablesToday, 'initiator', [
                    "timesent >= '{$todayStart}'",
                    "timesent <= '{$currentTime}'"
                ]);

                // SMS by delivery status - today (using deliverystatus2 for accurate status)
                $smsByDeliveryStatus = $this->groupByFromAllTables($tablesToday, 'deliverystatus2', [
                    "timesent >= '{$todayStart}'",
                    "timesent <= '{$currentTime}'",
                    "deliverystatus1 = 'acked'"
                ]);

                // Hourly breakdown for today (for chart)
                $hourlyBreakdown = $this->getHourlyBreakdownFromAllTables($tablesToday, [
                    "timesent >= '{$todayStart}'",
                    "timesent <= '{$currentTime}'"
                ]);

                // Top 5 customers by SMS today
                $topCustomers = $this->getTopCustomersFromAllTables($tablesToday, [
                    "timesent >= '{$todayStart}'",
                    "timesent <= '{$currentTime}'"
                ], 5);
                
                // Get customer names
                $topCustomersWithNames = [];
                foreach ($topCustomers as $customer) {
                    $user = DB::table('users')->where('bigid', $customer->userref)->first();
                    $topCustomersWithNames[] = [
                        'name' => $user ? urldecode($user->busname ?: $user->contactname ?: $user->uname) : 'Unknown',
                        'count' => $customer->count,
                    ];
                }
                
                // Calculate average SMS per minute (based on last hour)
                $avgPerMinute = $smsPerHour > 0 ? round($smsPerHour / 60, 2) : 0;
                
                // Calculate SMS counts by delivery status
                $smsSuccess = $smsByDeliveryStatus['Delivered'] ?? 0;
                $smsPending = $smsByDeliveryStatus['pending'] ?? 0;
                // Failed = Non Delivered + Lost Notification + any other non-success/pending status
                $smsFailed = ($smsByDeliveryStatus['Non Delivered'] ?? 0)
                           + ($smsByDeliveryStatus['Lost Notification'] ?? 0)
                           + ($smsByDeliveryStatus['failed'] ?? 0)
                           + ($smsByDeliveryStatus['error'] ?? 0);

                return [
                    'sms_per_second' => $smsPerSecond,
                    'sms_per_minute' => $smsPerMinute,
                    'sms_per_hour' => $smsPerHour,
                    'sms_today' => $smsToday,
                    'avg_per_minute' => $avgPerMinute,
                    'sms_api' => $smsByInitiator['API'] ?? 0,
                    'sms_dashboard' => ($smsByInitiator['Dashboard'] ?? 0) + ($smsByInitiator['DASHBOARD'] ?? 0) + ($smsByInitiator['WEB'] ?? 0) + ($smsByInitiator[''] ?? 0),
                    'sms_success' => $smsSuccess,
                    'sms_failed' => $smsFailed,
                    'sms_pending' => $smsPending,
                    'hourly_breakdown' => $hourlyBreakdown,
                    'top_customers' => $topCustomersWithNames,
                    'by_status' => $smsByDeliveryStatus,
                    'by_initiator' => $smsByInitiator,
                ];
            });
            
            return [
                'smsStats' => $stats,
                'serverTime' => $now->format('Y-m-d H:i:s'),
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting system status data: ' . $e->getMessage());
            return [
                'smsStats' => $this->getEmptyStats(),
                'serverTime' => Carbon::now()->format('Y-m-d H:i:s'),
                'smsStatsError' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get empty stats array
     */
    private function getEmptyStats()
    {
        return [
            'sms_per_second' => 0,
            'sms_per_minute' => 0,
            'sms_per_hour' => 0,
            'sms_today' => 0,
            'avg_per_minute' => 0,
            'sms_api' => 0,
            'sms_dashboard' => 0,
            'sms_success' => 0,
            'sms_failed' => 0,
            'sms_pending' => 0,
            'hourly_breakdown' => [],
            'top_customers' => [],
            'by_status' => [],
            'by_initiator' => [],
        ];
    }

    /**
     * Count records from all smsg_log tables
     */
    private function countFromAllTables($tables, $conditions = [])
    {
        $queries = collect($tables)->map(function ($tableName) use ($conditions) {
            $query = "SELECT COUNT(*) as cnt FROM {$tableName}";
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            return $query;
        });
        
        $unionQuery = implode(' UNION ALL ', $queries->toArray());
        $sql = "SELECT SUM(cnt) as total FROM ({$unionQuery}) as combined";
        
        $result = DB::select($sql);
        return $result[0]->total ?? 0;
    }

    /**
     * Group by field from all smsg_log tables
     */
    private function groupByFromAllTables($tables, $field, $conditions = [])
    {
        $queries = collect($tables)->map(function ($tableName) use ($field, $conditions) {
            $query = "SELECT {$field}, COUNT(*) as cnt FROM {$tableName}";
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            $query .= " GROUP BY {$field}";
            return $query;
        });
        
        $unionQuery = implode(' UNION ALL ', $queries->toArray());
        $sql = "SELECT {$field}, SUM(cnt) as count FROM ({$unionQuery}) as combined GROUP BY {$field}";
        
        $results = DB::select($sql);
        $data = [];
        foreach ($results as $row) {
            $data[$row->$field ?? ''] = (int) $row->count;
        }
        return $data;
    }

    /**
     * Get hourly breakdown from all smsg_log tables
     */
    private function getHourlyBreakdownFromAllTables($tables, $conditions = [])
    {
        $queries = collect($tables)->map(function ($tableName) use ($conditions) {
            $query = "SELECT SUBSTRING(timesent, 9, 2) as hour, COUNT(*) as cnt FROM {$tableName}";
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            $query .= " GROUP BY SUBSTRING(timesent, 9, 2)";
            return $query;
        });
        
        $unionQuery = implode(' UNION ALL ', $queries->toArray());
        $sql = "SELECT hour, SUM(cnt) as count FROM ({$unionQuery}) as combined GROUP BY hour ORDER BY hour";
        
        $results = DB::select($sql);
        $data = [];
        foreach ($results as $row) {
            $data[$row->hour] = (int) $row->count;
        }
        return $data;
    }

    /**
     * Get top customers from all smsg_log tables
     */
    private function getTopCustomersFromAllTables($tables, $conditions = [], $limit = 5)
    {
        $queries = collect($tables)->map(function ($tableName) use ($conditions) {
            $query = "SELECT userref, COUNT(*) as cnt FROM {$tableName}";
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            $query .= " GROUP BY userref";
            return $query;
        });
        
        $unionQuery = implode(' UNION ALL ', $queries->toArray());
        $sql = "SELECT userref, SUM(cnt) as count FROM ({$unionQuery}) as combined GROUP BY userref ORDER BY count DESC LIMIT {$limit}";
        
        return DB::select($sql);
    }

    /**
     * Get real-time SMS stats via AJAX
     */
    public function getRealtimeSmsStats()
    {
        $data = $this->getSystemStatusData();
        return response()->json($data);
    }

    /**
     * Get customer settings data
     */
    private function getCustomerSettingsData()
    {
        // Get settings
        $defaultMarginPercentage = CustomerSetting::getValue('default_price_margin_percentage', 10);
        $globalMaintenanceMode = CustomerSetting::getValue('global_maintenance_mode', false);
        $maintenanceMessage = CustomerSetting::getValue('maintenance_message', 'The site is currently under maintenance. Please try again later.');
        
        // Get customers in maintenance
        $customersInMaintenance = CustomerMaintenance::with('user')
            ->where('is_enabled', true)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get all customers for selection (legacy OLD SYSTEM listing query)
        $allCustomers = $this->getLegacyCustomers();
        
        return [
            'defaultMarginPercentage' => $defaultMarginPercentage,
            'globalMaintenanceMode' => $globalMaintenanceMode,
            'maintenanceMessage' => $maintenanceMessage,
            'customersInMaintenance' => $customersInMaintenance,
            'allCustomers' => $allCustomers,
        ];
    }

    /**
     * Save customer settings
     */
    public function saveCustomerSettings(Request $request)
    {
        // Determine which form was submitted based on presence of fields
        $isGeneralSettings = $request->has('default_margin_percentage');
        $isGlobalMaintenance = $request->has('save_global_maintenance');
        
        // Validate based on which form was submitted
        if ($isGeneralSettings) {
            $request->validate([
                'default_margin_percentage' => 'required|numeric|min:0|max:100',
            ]);
        }
        
        if ($isGlobalMaintenance) {
            $request->validate([
                'maintenance_message' => 'nullable|string|max:1000',
            ]);
        }

        $adminUser = Session::get('admin_user');
        $adminId = $adminUser['id'] ?? null;

        try {
            // Save margin percentage (only if present in request)
            if ($isGeneralSettings) {
                CustomerSetting::setValue(
                    'default_price_margin_percentage',
                    $request->default_margin_percentage,
                    'decimal',
                    'Default price margin percentage for new customers',
                    $adminId
                );
                
                return redirect()->route('admin.settings', ['tab' => 'customer-settings', 'subtab' => 'general-settings'])
                    ->with('success', 'General settings saved successfully!');
            }

            // Save global maintenance mode (only if this form was submitted)
            if ($isGlobalMaintenance) {
                CustomerSetting::setValue(
                    'global_maintenance_mode',
                    $request->has('global_maintenance_mode') ? '1' : '0',
                    'boolean',
                    'Enable maintenance mode for all customers',
                    $adminId
                );

                // Save maintenance message
                CustomerSetting::setValue(
                    'maintenance_message',
                    $request->maintenance_message ?? 'The site is currently under maintenance. Please try again later.',
                    'string',
                    'Message to display during maintenance',
                    $adminId
                );
                
                $status = $request->has('global_maintenance_mode') ? 'enabled' : 'disabled';
                return redirect()->route('admin.settings', ['tab' => 'customer-settings', 'subtab' => 'global-maintenance'])
                    ->with('success', "Global maintenance mode {$status} successfully!");
            }

            return redirect()->route('admin.settings', ['tab' => 'customer-settings'])
                ->with('success', 'Customer settings saved successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to save customer settings: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save settings: ' . $e->getMessage());
        }
    }

    /**
     * Add customer to maintenance mode
     */
    public function addCustomerMaintenance(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:users,id',
            'maintenance_message' => 'nullable|string|max:500',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after_or_equal:start_time',
        ]);

        $adminUser = Session::get('admin_user');
        $adminId = $adminUser['id'] ?? null;

        try {
            $addedCount = 0;
            
            foreach ($request->customer_ids as $customerId) {
                $customer = User::find($customerId);
                
                if (!$customer) continue;
                
                // Check if already in maintenance
                $exists = CustomerMaintenance::where('user_id', $customerId)
                    ->where('is_enabled', true)
                    ->exists();
                
                if (!$exists) {
                    CustomerMaintenance::create([
                        'user_id' => $customerId,
                        'user_bigid' => $customer->bigid,
                        'is_enabled' => true,
                        'maintenance_message' => $request->maintenance_message,
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                        'created_by' => $adminId,
                    ]);
                    $addedCount++;
                }
            }

            if ($addedCount > 0) {
                return redirect()->route('admin.settings', ['tab' => 'customer-settings', 'subtab' => 'customer-maintenance'])
                    ->with('success', "{$addedCount} customer(s) added to maintenance mode.");
            } else {
                return redirect()->route('admin.settings', ['tab' => 'customer-settings', 'subtab' => 'customer-maintenance'])
                    ->with('warning', 'Selected customers are already in maintenance mode.');
            }
        } catch (\Exception $e) {
            Log::error('Failed to add customer to maintenance: ' . $e->getMessage());
            return redirect()->route('admin.settings', ['tab' => 'customer-settings', 'subtab' => 'customer-maintenance'])
                ->with('error', 'Failed to add customer to maintenance: ' . $e->getMessage());
        }
    }

    /**
     * Remove customer from maintenance mode
     */
    public function removeCustomerMaintenance(Request $request)
    {
        $request->validate([
            'maintenance_id' => 'required|exists:customer_maintenance,id',
        ]);

        try {
            $maintenance = CustomerMaintenance::find($request->maintenance_id);
            
            if ($maintenance) {
                $maintenance->is_enabled = false;
                $maintenance->save();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Customer removed from maintenance mode.'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Maintenance record not found.'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to remove customer from maintenance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove from maintenance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer maintenance record
     */
    public function deleteCustomerMaintenance(Request $request)
    {
        $request->validate([
            'maintenance_id' => 'required|exists:customer_maintenance,id',
        ]);

        try {
            CustomerMaintenance::destroy($request->maintenance_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Maintenance record deleted successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete maintenance record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers for maintenance selection
     */
    public function searchCustomers(Request $request)
    {
        $search = $request->get('q', '');
        
        $needle = mb_strtolower($search);
        $customers = $this->getLegacyCustomers()
            ->filter(function ($c) use ($needle) {
                if ($needle === '') {
                    return true;
                }
                $haystack = mb_strtolower(
                    ($c->busname ?? '') . ' ' .
                    ($c->contactname ?? '') . ' ' .
                    ($c->contactemail ?? '') . ' ' .
                    ($c->uname ?? '')
                );
                return str_contains($haystack, $needle);
            })
            ->take(20)
            ->values();
        
        return response()->json($customers);
    }

    /**
     * Store a new environment variable
     */
    public function storeEnv(Request $request)
    {
        $request->validate([
            'key' => 'required|string|regex:/^[A-Z_][A-Z0-9_]*$/|max:255',
            'value' => 'required|string',
            'section' => 'nullable|string',
        ], [
            'key.regex' => 'The key must contain only uppercase letters, numbers, and underscores, and must start with a letter or underscore.'
        ]);

        $key = strtoupper($request->key);
        $value = $request->value;
        $section = $request->section ?? 'Custom';

        // Check if key already exists
        if ($this->envKeyExists($key)) {
            return redirect()->back()->with('error', "Environment variable '{$key}' already exists. Please use edit to update it.");
        }

        // Add the new variable with section comment if needed
        if ($this->addEnvVariable($key, $value, $section)) {
            // Clear config cache
            Artisan::call('config:clear');
            
            return redirect()->back()->with('success', "Environment variable '{$key}' has been added successfully to {$section} section.");
        }

        return redirect()->back()->with('error', 'Failed to add environment variable. Please check file permissions.');
    }

    /**
     * Update an existing environment variable
     */
    public function updateEnv(Request $request)
    {
        $request->validate([
            'old_key' => 'required|string',
            'key' => 'required|string|regex:/^[A-Z_][A-Z0-9_]*$/|max:255',
            'value' => 'required|string',
        ]);

        $oldKey = strtoupper($request->old_key);
        $newKey = strtoupper($request->key);
        $value = $request->value;

        if (!$this->envKeyExists($oldKey)) {
            return redirect()->back()->with('error', "Environment variable '{$oldKey}' does not exist.");
        }

        // Update the variable
        if ($this->updateEnvVariable($oldKey, $newKey, $value)) {
            // Clear config cache
            Artisan::call('config:clear');
            
            return redirect()->back()->with('success', "Environment variable has been updated successfully.");
        }

        return redirect()->back()->with('error', 'Failed to update environment variable. Please check file permissions.');
    }

    /**
     * Delete an environment variable
     */
    public function deleteEnv(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        $key = strtoupper($request->key);

        if (!$this->envKeyExists($key)) {
            return redirect()->back()->with('error', "Environment variable '{$key}' does not exist.");
        }

        // Prevent deletion of critical variables
        $criticalKeys = ['APP_KEY', 'APP_ENV', 'APP_DEBUG', 'APP_NAME', 'APP_URL', 'DB_CONNECTION'];
        if (in_array($key, $criticalKeys)) {
            return redirect()->back()->with('error', "Cannot delete critical environment variable '{$key}'.");
        }

        // Delete the variable
        if ($this->deleteEnvVariable($key)) {
            // Clear config cache
            Artisan::call('config:clear');
            
            return redirect()->back()->with('success', "Environment variable '{$key}' has been deleted successfully.");
        }

        return redirect()->back()->with('error', 'Failed to delete environment variable. Please check file permissions.');
    }

    /**
     * Get environment variables grouped by sections
     */
    private function getEnvVariablesBySection()
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            return [];
        }

        $envContent = File::get($envPath);
        $lines = explode("\n", $envContent);
        
        $sections = [];
        $currentSection = 'General';
        $sectionOrder = [];
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Detect section comments (e.g., # Database Configuration)
            if (preg_match('/^#\s*(.+?)\s*$/', $trimmedLine, $matches)) {
                $sectionName = trim($matches[1]);
                // Only treat it as a section header if it's meaningful
                if (!empty($sectionName) && !preg_match('/^[-=]+$/', $sectionName)) {
                    $currentSection = $sectionName;
                    if (!in_array($currentSection, $sectionOrder)) {
                        $sectionOrder[] = $currentSection;
                    }
                }
                continue;
            }
            
            // Skip empty lines
            if (empty($trimmedLine)) {
                continue;
            }
            
            // Parse key=value
            if (strpos($trimmedLine, '=') !== false) {
                list($key, $value) = explode('=', $trimmedLine, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                // Auto-detect section if not explicitly set
                if ($currentSection === 'General') {
                    $currentSection = $this->detectSectionFromKey($key);
                    if (!in_array($currentSection, $sectionOrder)) {
                        $sectionOrder[] = $currentSection;
                    }
                }
                
                if (!isset($sections[$currentSection])) {
                    $sections[$currentSection] = [];
                }
                
                $sections[$currentSection][$key] = $value;
            }
        }
        
        // Order sections nicely
        $orderedSections = [];
        $preferredOrder = ['Application', 'Database', 'Cache', 'Queue', 'Mail', 'AWS', 'Broadcasting', 'SMS', 'Payment', 'API'];
        
        foreach ($preferredOrder as $section) {
            if (isset($sections[$section])) {
                $orderedSections[$section] = $sections[$section];
                unset($sections[$section]);
            }
        }
        
        // Add remaining sections
        foreach ($sections as $section => $variables) {
            $orderedSections[$section] = $variables;
        }
        
        return $orderedSections;
    }

    /**
     * Detect section from environment key
     */
    private function detectSectionFromKey($key)
    {
        $patterns = [
            'Application' => ['APP_', 'LOG_', 'ASSET_'],
            'Database' => ['DB_', 'REDIS_', 'MYSQL_', 'PGSQL_', 'SQLSRV_'],
            'Cache' => ['CACHE_', 'MEMCACHED_', 'SESSION_'],
            'Queue' => ['QUEUE_', 'RABBITMQ_', 'SQS_'],
            'Mail' => ['MAIL_', 'SMTP_', 'MAILGUN_', 'POSTMARK_', 'SES_'],
            'AWS' => ['AWS_'],
            'Broadcasting' => ['BROADCAST_', 'PUSHER_', 'ABLY_'],
            'SMS' => ['SMS_', 'VONAGE_', 'NEXMO_', 'TWILIO_', 'SMPP_'],
            'Payment' => ['STRIPE_', 'PAYPAL_', 'RAZORPAY_', 'PAYMENT_'],
            'API' => ['API_', 'VITE_'],
        ];
        
        foreach ($patterns as $section => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    return $section;
                }
            }
        }
        
        return 'Custom';
    }

    /**
     * Get all environment variables from .env file (legacy method for backward compatibility)
     */
    private function getAllEnvVariables()
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            return [];
        }

        $envContent = File::get($envPath);
        $lines = explode("\n", $envContent);
        $variables = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                $variables[$key] = $value;
            }
        }

        ksort($variables);
        return $variables;
    }

    /**
     * Check if an environment variable key exists
     */
    private function envKeyExists($key)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);
        $pattern = "/^{$key}=/m";
        
        return preg_match($pattern, $envContent) === 1;
    }

    /**
     * Add a new environment variable
     */
    private function addEnvVariable($key, $value, $section = 'Custom')
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);
        
        // Format the value (add quotes if it contains spaces or special characters)
        $formattedValue = $this->formatEnvValue($value);
        
        // Check if section comment exists
        $sectionComment = "# {$section}";
        $sectionExists = strpos($envContent, $sectionComment) !== false;
        
        if ($sectionExists) {
            // Add to existing section
            $pattern = "/(# {$section}[^\n]*\n)/";
            $replacement = "$1{$key}={$formattedValue}\n";
            $newContent = preg_replace($pattern, $replacement, $envContent);
            
            return File::put($envPath, $newContent) !== false;
        } else {
            // Add new section at the end
            $newLine = "\n# {$section}\n{$key}={$formattedValue}\n";
            return File::append($envPath, $newLine);
        }
    }

    /**
     * Update an existing environment variable
     */
    private function updateEnvVariable($oldKey, $newKey, $value)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);
        $formattedValue = $this->formatEnvValue($value);
        
        // Pattern to match the old key
        $pattern = "/^{$oldKey}=.*/m";
        $replacement = "{$newKey}={$formattedValue}";
        
        $newContent = preg_replace($pattern, $replacement, $envContent);
        
        return File::put($envPath, $newContent) !== false;
    }

    /**
     * Delete an environment variable
     */
    private function deleteEnvVariable($key)
    {
        $envPath = base_path('.env');
        
        if (!File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);
        
        // Pattern to match the key and its value (including the newline)
        $pattern = "/^{$key}=.*\n?/m";
        
        $newContent = preg_replace($pattern, '', $envContent);
        
        return File::put($envPath, $newContent) !== false;
    }

    /**
     * Format environment variable value
     */
    private function formatEnvValue($value)
    {
        // If value contains spaces, special characters, or is empty, wrap in quotes
        if (empty($value) || preg_match('/[\s#"\']/', $value)) {
            // Escape existing quotes
            $value = str_replace('"', '\\"', $value);
            return "\"{$value}\"";
        }
        
        return $value;
    }

    /**
     * Get log data using LogController logic
     */
    private function getLogData(Request $request)
    {
        try {
            $logPath = storage_path('logs');
            $selectedDate = $request->get('date', date('Y-m-d'));
            $selectedLevel = $request->get('level', 'all');
            $search = $request->get('search', '');
            $perPage = (int) $request->get('per_page', 50);
            
            // Validate per_page
            $allowedPerPage = [25, 50, 100, 200];
            if (!in_array($perPage, $allowedPerPage)) {
                $perPage = 50;
            }
            
            // Get all available log dates from log files
            $availableDates = $this->getAvailableLogDates($logPath);
            
            // Get log content for selected date
            $logFile = $this->findLogFile($logPath, $selectedDate);
            $logs = [];
            $totalLogs = 0;
            
            if ($logFile && File::exists($logFile)) {
                $logs = $this->parseLogFile($logFile, $selectedLevel, $search);
                $totalLogs = count($logs);
                
                // Paginate manually
                $currentPage = (int) $request->get('page', 1);
                $offset = ($currentPage - 1) * $perPage;
                $logs = array_slice($logs, $offset, $perPage);
            }
            
            return [
                'logs' => $logs,
                'availableDates' => $availableDates,
                'selectedDate' => $selectedDate,
                'selectedLevel' => $selectedLevel,
                'search' => $search,
                'totalLogs' => $totalLogs,
                'currentPage' => (int) $request->get('page', 1),
                'perPage' => $perPage
            ];
        } catch (\Exception $e) {
            Log::error('Error viewing logs in settings: ' . $e->getMessage());
            return [
                'logs' => [],
                'availableDates' => [],
                'selectedDate' => date('Y-m-d'),
                'selectedLevel' => 'all',
                'search' => '',
                'totalLogs' => 0,
                'currentPage' => 1,
                'perPage' => 50,
                'error' => 'Failed to load logs: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Find log file for a specific date
     */
    private function findLogFile($logPath, $date)
    {
        // Format 1: storage/logs/YYYY-MM-DD/laravel.log (directory-based)
        $directoryBasedLog = $logPath . '/' . $date . '/laravel.log';
        if (File::exists($directoryBasedLog)) {
            return $directoryBasedLog;
        }
        
        // Format 2: storage/logs/laravel-YYYY-MM-DD.log (flat file)
        $flatFileLog = $logPath . '/laravel-' . $date . '.log';
        if (File::exists($flatFileLog)) {
            return $flatFileLog;
        }
        
        return null;
    }

    /**
     * Get all available log dates from log files
     */
    private function getAvailableLogDates($logPath)
    {
        $dates = [];
        
        if (!File::isDirectory($logPath)) {
            return $dates;
        }
        
        // Check for flat file format: laravel-YYYY-MM-DD.log
        $files = File::files($logPath);
        foreach ($files as $file) {
            $filename = $file->getFilename();
            
            if (preg_match('/^laravel-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                $dates[] = $matches[1];
            }
        }
        
        // Check for directory-based format: YYYY-MM-DD/laravel.log
        $directories = File::directories($logPath);
        foreach ($directories as $directory) {
            $dirName = basename($directory);
            
            if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $dirName, $matches)) {
                if (File::exists($directory . '/laravel.log')) {
                    $dates[] = $matches[1];
                }
            }
        }
        
        // Remove duplicates and sort dates in descending order (newest first)
        $dates = array_unique($dates);
        rsort($dates);
        
        return $dates;
    }

    /**
     * Parse log file and extract log entries
     */
    private function parseLogFile($logFile, $levelFilter = 'all', $searchTerm = '')
    {
        $logs = [];
        
        if (!File::exists($logFile)) {
            return $logs;
        }
        
        // Memory guard: a single day's laravel.log can balloon to tens of MB (e.g. worker
        // reconnect spam). Loading the whole file into memory + regex-matching it exhausts
        // the PHP memory_limit and 500s the ENTIRE settings page (index() calls this on every
        // load). For large files read only the TAIL (most recent entries) and cap the count.
        $maxBytes = 3 * 1024 * 1024;   // parse at most the last 3 MB
        $maxEntries = 5000;            // and keep at most this many parsed entries

        $size = @filesize($logFile);
        if ($size !== false && $size > $maxBytes) {
            $fh = @fopen($logFile, 'rb');
            if ($fh === false) {
                return $logs;
            }
            fseek($fh, -$maxBytes, SEEK_END);
            $content = fread($fh, $maxBytes);
            fclose($fh);
            // We seeked into the middle of an entry — drop the partial leading fragment so
            // parsing starts at the first complete "[YYYY-MM-DD ..." entry.
            if (preg_match('/\n(\[\d{4}-\d{2}-\d{2}\s)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                $content = substr($content, $m[1][1]);
            }
        } else {
            $content = File::get($logFile);
        }

        // Pattern to match Laravel log entries
        $pattern = '/\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s+(.*?)(?=\[\d{4}-\d{2}-\d{2}|\z)/s';

        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $timestamp = $match[1];
            $environment = $match[2];
            $level = strtoupper($match[3]);
            $message = trim($match[4]);
            
            // Apply level filter
            if ($levelFilter !== 'all' && strtoupper($levelFilter) !== $level) {
                continue;
            }
            
            // Apply search filter
            if (!empty($searchTerm) && stripos($message, $searchTerm) === false) {
                continue;
            }
            
            $logs[] = [
                'timestamp' => $timestamp,
                'environment' => $environment,
                'level' => $level,
                'message' => $message,
                'raw' => $match[0]
            ];

            // Hard cap so a pathological log can't grow $logs unbounded.
            if (count($logs) >= $maxEntries) {
                break;
            }
        }
        
        // Reverse to show newest first
        return array_reverse($logs);
    }

    /**
     * Clear application cache
     */
    public function clearApplicationCache()
    {
        try {
            Artisan::call('cache:clear');
            return redirect()->back()->with('success', 'Application cache cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to clear application cache: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear application cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear configuration cache
     */
    public function clearConfigCache()
    {
        try {
            Artisan::call('config:clear');
            return redirect()->back()->with('success', 'Configuration cache cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to clear config cache: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear config cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear route cache
     */
    public function clearRouteCache()
    {
        try {
            Artisan::call('route:clear');
            return redirect()->back()->with('success', 'Route cache cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to clear route cache: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear route cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear view cache
     */
    public function clearViewCache()
    {
        try {
            Artisan::call('view:clear');
            return redirect()->back()->with('success', 'View cache cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to clear view cache: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear view cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear event cache
     */
    public function clearEventCache()
    {
        try {
            Artisan::call('event:clear');
            return redirect()->back()->with('success', 'Event cache cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to clear event cache: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear event cache: ' . $e->getMessage());
        }
    }

    /**
     * Clear compiled classes
     */
    public function clearCompiledCache()
    {
        try {
            Artisan::call('clear-compiled');
            return redirect()->back()->with('success', 'Compiled classes cleared successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to clear compiled: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear compiled: ' . $e->getMessage());
        }
    }

    /**
     * Optimize application
     */
    public function optimizeApplication()
    {
        try {
            Artisan::call('optimize');
            return redirect()->back()->with('success', 'Application optimized successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to optimize: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to optimize: ' . $e->getMessage());
        }
    }

    /**
     * Clear all caches at once
     */
    public function clearAllCaches()
    {
        try {
            $results = [];
            
            // Clear application cache
            Artisan::call('cache:clear');
            $results[] = 'Application cache';
            
            // Clear config cache
            Artisan::call('config:clear');
            $results[] = 'Configuration cache';
            
            // Clear route cache
            Artisan::call('route:clear');
            $results[] = 'Route cache';
            
            // Clear view cache
            Artisan::call('view:clear');
            $results[] = 'View cache';
            
            // Clear event cache
            Artisan::call('event:clear');
            $results[] = 'Event cache';
            
            // Clear compiled
            Artisan::call('clear-compiled');
            $results[] = 'Compiled classes';
            
            $message = 'All caches cleared successfully! (' . implode(', ', $results) . ')';
            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Failed to clear all caches: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to clear all caches: ' . $e->getMessage());
        }
    }

    /**
     * Cache configurations
     */
    public function cacheConfig()
    {
        try {
            Artisan::call('config:cache');
            return redirect()->back()->with('success', 'Configuration cached successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to cache config: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cache config: ' . $e->getMessage());
        }
    }

    /**
     * Cache routes
     */
    public function cacheRoutes()
    {
        try {
            Artisan::call('route:cache');
            return redirect()->back()->with('success', 'Routes cached successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to cache routes: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cache routes: ' . $e->getMessage());
        }
    }

    /**
     * Cache views
     */
    public function cacheViews()
    {
        try {
            Artisan::call('view:cache');
            return redirect()->back()->with('success', 'Views cached successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to cache views: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to cache views: ' . $e->getMessage());
        }
    }
}
