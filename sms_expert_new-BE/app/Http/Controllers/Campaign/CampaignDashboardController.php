<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\SmsgCampaign;
use App\Models\User;
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use App\Services\Queue\SmsQueueService;
use App\Services\Queue\CampaignQueueService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;



class CampaignDashboardController extends Controller
{
    protected $bulkThroughputService;
    protected $walletValidationService;
    protected $smsQueueService;
    protected $campaignQueueService;

    public function __construct()
    {
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();

        // Initialize SMS Queue Service
        try {
            $this->smsQueueService = new SmsQueueService();
        } catch (\Exception $e) {
            Log::error('Failed to initialize SMS Queue service: ' . $e->getMessage());
            $this->smsQueueService = null;
        }

        // Initialize Campaign Queue Service
        try {
            $this->campaignQueueService = new CampaignQueueService();
        } catch (\Exception $e) {
            Log::error('Failed to initialize Campaign Queue service: ' . $e->getMessage());
            $this->campaignQueueService = null;
        }
    }

    /**
     * Display the campaign dashboard
     */
    public function index()
    {
        $user = Session::get('user_info');

        // Check tour status for first-time users
        $showCampaignTour = false;
        if (isset($user['bigid'])) {
            $userOption = DB::table('useroption')
                ->where('userref', $user['bigid'])
                ->first();
            if ($userOption && !$userOption->campaign_tour_completed) {
                $showCampaignTour = true;
            }
        }

        return view('campaign.dashboard.dashboard', compact('user', 'showCampaignTour'));
    }

    /**
     * Display quick campaign form (Submit new SMS campaign - quick)
     */
    public function quickCampaign()
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;

        // Get available sender IDs from user's keywords
        $senderIds = [];
        if ($userref) {
            $senderIds = DB::table('itagg_instance as i')
                ->join('smsshortcodes as s', 'i.smsshortcodes_id', '=', 's.id')
                ->where('i.users_bigid', $userref)
                ->where('i.expiry', '>=', date('Y-m-d'))
                ->where('i.keyword', '*')
                ->where('i.active', 1)
                ->pluck('s.number')
                ->toArray();
        }

        return view('campaign.quick.index', compact('user', 'senderIds'));
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode($phoneNumber)
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        $countries = DB::table('country')
            ->select('dialcode', 'iso_code', 'id')
            ->orderByRaw('LENGTH(dialcode) DESC')
            ->get();

        foreach ($countries as $country) {
            if (substr($phoneNumber, 0, strlen($country->dialcode)) === $country->dialcode) {
                return $country;
            }
        }

        return DB::table('country')
            ->where('dialcode', '44')
            ->first();
    }

    /**
     * Calculate SMS parts based on message length
     */
    private function calculateSmsParts($message): int
    {
        $length = mb_strlen($message, 'UTF-8');

        if ($length <= 160) {
            return 1;
        }

        return (int) ceil($length / 153);
    }

    /**
     * Process quick campaign submission - Using RabbitMQ queue
     */
    public function submitQuickCampaign(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;

        if (!$userref) {
            return redirect()->back()->with('error', 'User session expired. Please login again.');
        }

        // Get user from database
        $getUserId = User::where('bigid', $userref)->first();
        if (!$getUserId) {
            return redirect()->back()->with('error', 'User not found.');
        }

        // Validation
        $errors = [];

        $campaignname = $request->input('campaignname', '');
        $quicksenderid1 = $request->input('quicksenderid1', 'choose');
        $quicksenderid2 = $request->input('quicksenderid2', '');
        $quickrecipients = $request->input('quickrecipients', '');
        $quickmsg = $request->input('quickmsg', '');
        $routeletter = $request->input('routeletter', '');

        if (empty($campaignname)) {
            $errors[] = 'Campaign name must be entered.';
        }

        // Sender ID validation
        $quicksenderid = '';
        if ($quicksenderid1 == 'choose') {
            $errors[] = 'Sender ID must be selected.';
        } elseif ($quicksenderid1 == 'useotherbelow' && empty(trim($quicksenderid2))) {
            $errors[] = "'Other' Sender ID cannot be blank.";
        } elseif ($quicksenderid1 != 'choose' && $quicksenderid1 != 'useotherbelow' && !empty(trim($quicksenderid2))) {
            $errors[] = "Sender ID must be chosen from dropdown or 'other', not both.";
        } elseif ($quicksenderid1 == 'useotherbelow' && !empty(trim($quicksenderid2))) {
            $quicksenderid = $quicksenderid2;
        } elseif ($quicksenderid1 != 'choose' && $quicksenderid1 != 'useotherbelow') {
            $quicksenderid = $quicksenderid1;
        } else {
            $errors[] = 'Sender ID is invalid.';
        }

        if (empty(trim($quickrecipients))) {
            $errors[] = 'There must be at least one valid recipient.';
        }

        if (empty(trim($quickmsg))) {
            $errors[] = 'Your campaign SMS wording cannot be blank.';
        }

        if (!empty($errors)) {
            return redirect()->back()
                ->with('error', implode(' ', $errors))
                ->withInput();
        }

        // Parse and count recipients
        $quickrecipients = trim($quickrecipients);
        $quickrecipientsarray = preg_split('/[\s,\n\r]+/', $quickrecipients);
        $recipientCount = count(array_filter($quickrecipientsarray, function ($num) {
            return !empty(trim($num));
        }));

        if ($recipientCount == 0) {
            return redirect()->back()
                ->with('error', 'No valid phone numbers found.')
                ->withInput();
        }

        // Check bulk throughput limit
        $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($userref);

        if (!$throughputCheck['allowed']) {
            Log::warning('Campaign SMS blocked due to throughput limit', [
                'user' => $userref,
                'campaign' => $campaignname,
                'limit' => $throughputCheck['limit'] ?? 0
            ]);

            return redirect()->back()
                ->with('error', 'You have reached your daily SMS send limit of ' . ($throughputCheck['limit'] ?? 0) . ' messages. Please contact support to increase your limit.')
                ->withInput();
        }

        // Check wallet balance (estimated)
        $walletCheck = $this->walletValidationService->validateWalletBalance($userref, $recipientCount, []);

        if (!$walletCheck['has_funds']) {
            Log::warning('Campaign SMS blocked due to insufficient funds', [
                'user' => $userref,
                'campaign' => $campaignname,
                'balance' => $walletCheck['current_balance'] ?? 0,
                'required' => $walletCheck['required_amount'] ?? 0
            ]);

            $errorMessage = 'Insufficient wallet funds. ';
            $errorMessage .= 'Current balance: £' . number_format($walletCheck['current_balance'] ?? 0, 2) . '. ';
            $errorMessage .= 'Required: £' . number_format($walletCheck['required_amount'] ?? 0, 2) . '. ';
            $errorMessage .= 'Please top up your SMS wallet to continue.';

            return redirect()->back()->with('error', $errorMessage)->withInput();
        }

        // Clean message
        $message = trim(str_replace(["\r\n", "\r", "\n"], ' ', $quickmsg));
        $from = trim($quicksenderid);

        // Generate unique campaign ID
        $campaignid = SmsgCampaign::generateCampaignId(5);
        $filename = "quick_campaign_{$userref}_{$campaignid}.csv";
        
        // Use storage path for cross-platform compatibility
        $filepath = CampaignQueueService::getCampaignFilePath($filename);

        // Create CSV file for campaign
        $file = fopen($filepath, 'w');
        $lineCount = 0;

        foreach ($quickrecipientsarray as $recipient) {
            $recipient = trim($recipient);
            if (!empty($recipient)) {
                // Remove non-numeric characters
                $recipient = preg_replace('/\D/', '', $recipient);
                if (!empty($recipient)) {
                    // CSV format: mobile, sendtime, originator, message, field1, field2, field3, route
                    $row = [$recipient, '', trim($from), $message, '', '', '', $routeletter];
                    fputcsv($file, $row);
                    $lineCount++;
                }
            }
        }
        fclose($file);

        // Insert campaign record
        $campaign = SmsgCampaign::create([
            'campaignid' => $campaignid,
            'userref' => $userref,
            'datetime' => date('YmdHis'),
            'campaignname' => $campaignname,
            'filename' => $filename,
            'numlines' => $lineCount,
            'numlinesdone' => 0,
            'status' => 'filewaiting',
            'uniqueid' => SmsgCampaign::generateUniqueId(32)
        ]);

        // Queue campaign for processing via RabbitMQ
        if ($this->campaignQueueService) {
            $queueResult = $this->campaignQueueService->queueCampaign([
                'campaign_id' => $campaignid,
                'campaign_row_id' => $campaign->id,
                'user_ref' => $userref,
                'filename' => $filename,
                'filepath' => $filepath,
                'num_lines' => $lineCount,
                'num_lines_done' => 0,
                'metadata' => [
                    'campaign_name' => $campaignname,
                    'sender_id' => $from,
                    'route' => $routeletter,
                    'source' => 'quick_campaign'
                ]
            ]);

            if ($queueResult['success']) {
                Log::info('Campaign queued to RabbitMQ', [
                    'campaign_id' => $campaignid,
                    'queue_id' => $queueResult['queue_id'] ?? null,
                    'line_count' => $lineCount
                ]);

                return redirect()->route('campaign.previous.list')
                    ->with('success', "Campaign \"{$campaignname}\" submitted successfully! {$lineCount} message(s) queued for processing. Check the status on this page.");
            } else {
                Log::error('Failed to queue campaign to RabbitMQ', [
                    'campaign_id' => $campaignid,
                    'error' => $queueResult['error'] ?? 'Unknown'
                ]);

                // Update campaign status to indicate queue failure
                SmsgCampaign::where('campaignid', $campaignid)
                    ->update(['status' => 'failed', 'statusinfo' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown')]);

                return redirect()->back()
                    ->with('error', 'Failed to queue campaign for processing. Please try again.')
                    ->withInput();
            }
        } else {
            // Fallback: Campaign will be processed by the existing daemon
            Log::info('Campaign created without RabbitMQ (fallback mode)', [
                'campaign_id' => $campaignid,
                'line_count' => $lineCount
            ]);

            return redirect()->route('campaign.previous.list')
                ->with('success', "Campaign \"{$campaignname}\" submitted successfully! {$lineCount} message(s) will be processed shortly.");
        }
    }

    /**
     * Display file upload campaign form (Submit new SMS campaign - file upload)
     */
    public function uploadCampaign()
    {
        $user = Session::get('user_info');
        return view('campaign.upload.index', compact('user'));
    }

    /**
     * Process file upload campaign submission - Using RabbitMQ queue
     */
    public function submitUploadCampaign(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;

        if (!$userref) {
            return redirect()->back()->with('error', 'User session expired. Please login again.');
        }

        $campaignname = $request->input('campaignname', '');
        $errors = [];

        if (empty($campaignname)) {
            $errors[] = 'Campaign name must be entered.';
        }

        if (!$request->hasFile('userfile')) {
            $errors[] = 'There was no file to upload.';
        } else {
            $file = $request->file('userfile');

            if (!$file->isValid()) {
                $errors[] = 'There was an error uploading your file.';
            } elseif (strtolower($file->getClientOriginalExtension()) !== 'csv') {
                $errors[] = 'Your file is not a CSV file. Filename extension should be ".csv".';
            } elseif ($file->getSize() > 104857600) { // 100MB
                $errors[] = 'Your file is too big. 100MB maximum.';
            }
        }

        if (!empty($errors)) {
            return redirect()->back()
                ->with('error', implode(' ', $errors))
                ->withInput();
        }

        // Generate campaign ID
        $campaignid = SmsgCampaign::generateCampaignId(5);
        $filename = "uploadedcampaign_{$userref}_{$campaignid}.csv";
        
        // Use storage path for cross-platform compatibility
        $filepath = CampaignQueueService::getCampaignFilePath($filename);

        // Move uploaded file
        $file = $request->file('userfile');
        copy($file->getPathname(), $filepath);

        // Count lines in file
        $handle = fopen($filepath, 'r');
        $lineCount = 0;
        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $lineCount++;
            }
        }
        fclose($handle);

        // Insert campaign record
        $campaign = SmsgCampaign::create([
            'campaignid' => $campaignid,
            'userref' => $userref,
            'datetime' => date('YmdHis'),
            'campaignname' => $campaignname,
            'filename' => $filename,
            'numlines' => $lineCount,
            'numlinesdone' => 0,
            'status' => 'filewaiting',
            'uniqueid' => SmsgCampaign::generateUniqueId(32)
        ]);

        // Queue campaign for processing via RabbitMQ
        if ($this->campaignQueueService) {
            $queueResult = $this->campaignQueueService->queueCampaign([
                'campaign_id' => $campaignid,
                'campaign_row_id' => $campaign->id,
                'user_ref' => $userref,
                'filename' => $filename,
                'filepath' => $filepath,
                'num_lines' => $lineCount,
                'num_lines_done' => 0,
                'metadata' => [
                    'campaign_name' => $campaignname,
                    'source' => 'file_upload'
                ]
            ]);

            if ($queueResult['success']) {
                Log::info('Campaign file queued to RabbitMQ', [
                    'campaign_id' => $campaignid,
                    'queue_id' => $queueResult['queue_id'] ?? null,
                    'line_count' => $lineCount
                ]);

                return redirect()->route('campaign.previous.list')
                    ->with('success', "Campaign file \"{$campaignname}\" uploaded successfully! {$lineCount} line(s) queued for processing. Check the status on this page.");
            } else {
                Log::error('Failed to queue campaign file to RabbitMQ', [
                    'campaign_id' => $campaignid,
                    'error' => $queueResult['error'] ?? 'Unknown'
                ]);

                // Campaign will be processed by existing daemon as fallback
                return redirect()->route('campaign.previous.list')
                    ->with('success', "Campaign file \"{$campaignname}\" uploaded successfully! System will process the file shortly.");
            }
        } else {
            // Fallback: Campaign will be processed by the existing daemon
            return redirect()->route('campaign.previous.list')
                ->with('success', "Campaign file \"{$campaignname}\" uploaded successfully! System will now process file and begin sending SMS.");
        }
    }

    /**
     * Display previous campaigns list (View previous SMS campaigns)
     */
    public function previousCampaigns(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;

        if (!$userref) {
            return redirect('/')->with('error', 'Session expired. Please login again.');
        }

        // Get user's short domain
        $userInfo = User::where('bigid', $userref)->first();
        $shortdomain = $userInfo->shortdomain ?? '';

        // Search filters
        $searchCampaignName = $request->input('searchcampaignname', '');
        $searchCampaignId = $request->input('searchcampaignid', '');

        // Build query
        $query = SmsgCampaign::forUser($userref)
            ->notDeleted()
            ->orderBy('datetime', 'desc');

        if (!empty($searchCampaignName)) {
            $query->where('campaignname', 'like', "%{$searchCampaignName}%");
        }

        if (!empty($searchCampaignId)) {
            $query->where('campaignid', 'like', "%{$searchCampaignId}%");
        }

        $campaigns = $query->get();

        return view('campaign.previous.index', compact('user', 'campaigns', 'shortdomain', 'searchCampaignName', 'searchCampaignId'));
    }

    /**
     * Pause a campaign
     */
    public function pauseCampaign(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $campaignref = $request->input('campaignref');

        if (!$userref || !$campaignref) {
            return redirect()->back()->with('error', 'Invalid request.');
        }

        $campaign = SmsgCampaign::where('userref', $userref)
            ->where('campaignid', $campaignref)
            ->first();

        if (!$campaign) {
            return redirect()->back()->with('error', 'Campaign not found.');
        }

        // Update campaign status
        $campaign->update([
            'statustmp' => $campaign->status,
            'status' => 'paused',
            'statusinfo' => ($campaign->statusinfo ?? '') . '. ' . date('jS M Y, g:i:sa') . ' pausing...'
        ]);

        // Update SMS log entries
        sleep(10); // Allow daemon to finish current operations
        $affectedRows = DB::table('smsg_log')
            ->where('userref', $userref)
            ->where('campaignref', $campaignref)
            ->whereIn('sentstatus', ['pending', 'hlrwait', 'no', 'tomorrowonward'])
            ->update([
                'sentstatustmp' => DB::raw('sentstatus'),
                'sentstatus' => 'pause'
            ]);

        $campaign->update([
            'statusinfo' => ($campaign->statusinfo ?? '') . "campaign + {$affectedRows} unsent sms"
        ]);

        return redirect()->back()->with('success', "Campaign '{$campaignref}' paused and {$affectedRows} unsent SMS suspended.");
    }

    /**
     * Resume a paused campaign
     */
    public function resumeCampaign(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $campaignref = $request->input('campaignref');

        if (!$userref || !$campaignref) {
            return redirect()->back()->with('error', 'Invalid request.');
        }

        $campaign = SmsgCampaign::where('userref', $userref)
            ->where('campaignid', $campaignref)
            ->first();

        if (!$campaign) {
            return redirect()->back()->with('error', 'Campaign not found.');
        }

        // Update campaign status
        $campaign->update([
            'status' => $campaign->statustmp ?? 'completed',
            'statustmp' => '',
            'statusinfo' => ($campaign->statusinfo ?? '') . '. ' . date('jS M Y, g:i:sa') . ' unpausing...'
        ]);

        // Update SMS log entries
        sleep(10);
        $affectedRows = DB::table('smsg_log')
            ->where('userref', $userref)
            ->where('campaignref', $campaignref)
            ->where('sentstatus', 'pause')
            ->update([
                'sentstatus' => DB::raw('sentstatustmp'),
                'sentstatustmp' => ''
            ]);

        $campaign->update([
            'statusinfo' => ($campaign->statusinfo ?? '') . "campaign + {$affectedRows} unsent sms"
        ]);

        return redirect()->back()->with('success', "Campaign '{$campaignref}' resumed and {$affectedRows} unsent SMS unsuspended.");
    }

    /**
     * Delete a campaign
     */
    public function deleteCampaign(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $campaignref = $request->input('campaignref');

        if (!$userref || !$campaignref) {
            return redirect()->back()->with('error', 'Invalid request.');
        }

        $campaign = SmsgCampaign::where('userref', $userref)
            ->where('campaignid', $campaignref)
            ->first();

        if (!$campaign) {
            return redirect()->back()->with('error', 'Campaign not found.');
        }

        // Update campaign status to deleted
        $campaign->update([
            'status' => 'deleted',
            'statusinfo' => ($campaign->statusinfo ?? '') . '. ' . date('jS M Y, g:i:sa') . ' deleting...'
        ]);

        // Mark unsent SMS as failed
        $smsDeleted = 0;
        for ($i = 0; $i < 2; $i++) {
            sleep(10);
            $affected = DB::table('smsg_log')
                ->where('userref', $userref)
                ->where('campaignref', $campaignref)
                ->whereIn('sentstatus', ['pending', 'hlrwait', 'no', 'pause', 'tomorrowonward'])
                ->update(['sentstatus' => 'fail']);
            $smsDeleted += $affected;
        }

        $campaign->update([
            'statusinfo' => ($campaign->statusinfo ?? '') . "campaign + {$smsDeleted} unsent sms"
        ]);

        return redirect()->back()->with('success', "Campaign '{$campaignref}' removed and {$smsDeleted} unsent SMS deleted.");
    }

    /**
     * Download campaign report
     */
    public function downloadCampaignReport(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $campaignref = $request->input('campaignref');

        if (!$userref || !$campaignref) {
            return response('Invalid request', 400);
        }

        // Verify campaign belongs to user
        $campaign = SmsgCampaign::where('userref', $userref)
            ->where('campaignid', $campaignref)
            ->first();

        if (!$campaign) {
            return response('Campaign not found', 404);
        }

        // Generate CSV report
        $filename = "campaignreport_{$campaignref}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($userref, $campaignref, $campaign) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, [
                'Time Reply Received',
                'Reply',
                'Reply Number',
                'Mobile',
                'Time Message Sent',
                'Delivery Status',
                'Time Delivered',
                'Sender ID',
                'Message',
                'Message Parts',
                'Campaign ID',
                'Campaign Name',
                'Original Time To Send'
            ]);

            // Get SMS logs from multiple tables (current and archived)
            $tables = ['smsg_log'];
            for ($i = 1; $i <= 11; $i++) {
                $tables[] = 'smsg_log_' . date('ym', strtotime("first day of -{$i} month"));
            }

            foreach ($tables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    $logs = DB::table("{$table} as l")
                        ->leftJoin('itagg_incominglog as i', function ($join) {
                            $join->on('l.mobnum', '=', 'i.source')
                                ->on('l.userref', '=', 'i.user_bigid')
                                ->on('l.originator', '=', 'i.dest')
                                ->whereRaw('i.recieved >= STR_TO_DATE(l.timesent, "%Y%m%d%H%i%s")');
                        })
                        ->where('l.userref', $userref)
                        ->where('l.campaignref', $campaignref)
                        ->select([
                            'l.text',
                            'l.mobnum',
                            'l.originator',
                            DB::raw('STR_TO_DATE(l.timesent, "%Y%m%d%H%i%s") as thetimesent'),
                            'l.deliverystatus2',
                            // Keep deliverytime2 as the raw YYYYMMDDHHMMSS string —
                            // OLD SYSTEM stores it in UTC. We convert to Europe/London
                            // in PHP below so Carbon handles BST/GMT correctly per row.
                            'l.deliverytime2 as deliverytime2_raw',
                            'l.numparts',
                            'i.msg',
                            'i.recieved',
                            'l.sentstatus',
                            'l.sentstatustext',
                            'l.dosendtime as thetimetosend'
                        ])
                        ->orderBy('l.mobnum')
                        ->orderBy('l.originator')
                        ->orderBy('i.recieved')
                        ->get();

                    // Internal smsg_log.deliverystatus2 values → human labels in
                    // the CSV. The OLD SYSTEM only used the user-facing labels
                    // ('Delivered', 'Non Delivered', 'Lost Notification') so
                    // anything that looks like internal state ('acked',
                    // 'pending', 'scheduled') gets translated for the report.
                    // Empty / NULL values stay blank (rendered as '' below).
                    $deliveryStatusLabels = [
                        'acked'        => 'Delivered',          // submitted to network, DLR not yet back — OLD SYSTEM displayed this as Delivered
                        'pending'      => 'Pending',            // waiting for SMPP queue worker
                        'scheduled'    => 'Scheduled',          // scheduled for future send
                        // Pass-through values (already correct OLD SYSTEM labels):
                        'Delivered'    => 'Delivered',
                        'Non Delivered'=> 'Non Delivered',
                        'Lost Notification' => 'Lost Notification',
                    ];

                    foreach ($logs as $log) {
                        $rawStatus = (string) ($log->deliverystatus2 ?? '');

                        if ($log->sentstatus == 'fail' && stripos($log->sentstatustext, 'blacklist') !== false) {
                            $deliverystatus2 = 'Not Sent/Blacklisted';
                        } else {
                            // Use the mapped label; for any unknown value fall
                            // back to the raw string so we don't accidentally
                            // hide a state the team relies on.
                            $deliverystatus2 = $deliveryStatusLabels[$rawStatus] ?? $rawStatus;
                        }

                        // "Date Finalised" = Send-at-Time + 1 second, shown only when the message
                        // is finalised (deliverytime2 populated). The carrier's SMPP done_date is
                        // unreliable (Vonage returns it in the destination tz, e.g. India = IST, with
                        // no marker), so it is NOT used — the finalised time is derived from the send
                        // time instead. Empty/zero deliverytime2 => blank.
                        $deliverytimeDisplay = '';
                        $raw = trim((string) ($log->deliverytime2_raw ?? ''));
                        if ($raw !== '' && ctype_digit($raw) && (int) $raw !== 0) {
                            $sendRaw = preg_replace('/\D/', '', (string) ($log->thetimetosend ?? ''));
                            if (strlen($sendRaw) === 14) {
                                try {
                                    $deliverytimeDisplay = \Carbon\Carbon::createFromFormat('YmdHis', $sendRaw, 'Europe/London')
                                        ->addSecond()
                                        ->format('Y-m-d H:i:s');
                                } catch (\Throwable $e) {
                                    $deliverytimeDisplay = '';
                                }
                            }
                        }

                        fputcsv($file, [
                            $log->recieved,
                            urldecode($log->msg ?? ''),
                            $log->msg ? 1 : 0,
                            $log->mobnum,
                            $log->thetimesent,
                            $deliverystatus2,
                            $deliverytimeDisplay,
                            $log->originator,
                            urldecode($log->text ?? ''),
                            $log->numparts,
                            $campaignref,
                            $campaign->campaignname,
                            $log->thetimetosend
                        ]);
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * View STOP blacklist
     */
    public function viewBlacklist()
    {
        $user = Session::get('user_info');
        return view('campaign.blacklist.index', compact('user'));
    }

    /**
     * Download blacklist report
     */
    public function downloadBlacklist()
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;

        if (!$userref) {
            return response('Invalid request', 400);
        }

        $filename = "iTagg_blacklist_report.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($userref) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['users mobile number', 'blacklisted date', 'virtual reply destination']);

            $blacklist = DB::table('itagg_outbound_blacklist as b')
                ->leftJoin('itagg_inbound_stopcommands as i', function ($join) {
                    $join->on('b.users_bigid', '=', 'i.users_bigid')
                        ->on('b.msisdn', '=', 'i.msisdn');
                })
                ->where('b.users_bigid', $userref)
                ->orderBy('b.date_blocked')
                ->select(['b.msisdn', 'b.date_blocked', 'i.dest'])
                ->get();

            foreach ($blacklist as $item) {
                $dest = $item->dest;
                if (empty($dest) || $dest == '0' || $dest == 'NULL') {
                    $dest = '';
                }
                fputcsv($file, [$item->msisdn, $item->date_blocked, $dest]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * View accounts
     */
    public function viewAccounts()
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $username = $user['username'] ?? '';

        if (!$userref) {
            return redirect('/')->with('error', 'Session expired. Please login again.');
        }

        // Get master and sub accounts
        $accounts = User::where(function ($query) use ($username) {
            $query->where('uname', $username)
                ->orWhere('masteruname', $username);
        })
            ->select([
                DB::raw("IF(uname = '{$username}', 0, 1) as mastersub"),
                'busname',
                'uname',
                'contactname',
                'contactemail',
                DB::raw('(smsg_wallet - smsg_server1_sent - smsg_server2_sent) as thewallet'),
                'bulk_throughput',
                DB::raw('(platkeywordwallet / platkeywordcost) as numkeywords'),
                'pword as subpwd'
            ])
            ->orderBy('mastersub')
            ->orderBy('busname')
            ->get();

        return view('campaign.accounts.index', compact('user', 'accounts'));
    }

    /**
     * Display add account form
     */
    public function addAccount()
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $username = $user['username'] ?? '';

        if (!$userref) {
            return redirect('/')->with('error', 'Session expired. Please login again.');
        }

        // Check if user has account manager access
        $userInfo = User::where('bigid', $userref)->first();
        if (!$userInfo) {
            return redirect()->route('campaign.accounts')->with('error', 'User not found.');
        }

        // Check if user is a master account (can create sub-accounts)
        $isMaster = ($userInfo->masteruname == $username) &&
            (strpos($userInfo->dashboardaccess ?? '', 'a') !== false);

        if (!$isMaster) {
            return redirect()->route('campaign.accounts')
                ->with('error', 'You do not have permission to add sub-accounts.');
        }

        return view('campaign.accounts.add', compact('user'));
    }

    /**
     * Store new sub-account
     */
    public function storeAccount(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $username = $user['username'] ?? '';
        $password = $user['password'] ?? '';

        if (!$userref) {
            return redirect('/')->with('error', 'Session expired. Please login again.');
        }

        // Validate request
        $request->validate([
            'contactname' => 'required|string|max:255',
            'busname' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'mobile' => 'nullable|string|max:50',
        ]);

        // Verify user has permission
        $masterUser = User::where('uname', $username)->first();
        if (!$masterUser || $masterUser->masteruname != $username) {
            return redirect()->route('campaign.accounts')
                ->with('error', 'You do not have permission to add sub-accounts.');
        }

        $newcontactname = $request->input('contactname');
        $newbusname = $request->input('busname');
        $newtheemail = $request->input('email');
        $newthephone = $request->input('phone', '');
        $newthemobile = $request->input('mobile', '');

        if (empty(trim($newbusname)) || empty(trim($newcontactname))) {
            return redirect()->back()
                ->with('error', 'Account not added due to missing contact name and/or business name.')
                ->withInput();
        }

        // Generate new user credentials
        $newuserid = md5(uniqid(rand(), 1));
        $newusr = substr($newuserid, 0, 8);
        $newpwd = substr($newuserid, 8, 8);

        // Generate unique NFC key
        $nfckey = $this->generateUniqueNfcKey();

        // Generate unique affiliate invite code
        $newinvitecode = $this->generateUniqueInviteCode();

        // Generate secret key
        $newsecretkey = strtoupper($this->generateUniqueId(32));

        $signip = $request->ip();

        try {
            // Insert user reminder
            DB::table('userreminder')->insert([
                'usersbigidref' => $newuserid,
                'reminderon' => 'n'
            ]);

            // Insert user option
            DB::table('useroption')->insert([
                'userref' => $newuserid,
                'api_premrate_blocked' => 1,
                'can_use_location_lookup_api' => 'no',
                'explanation' => 'sub account for master account: ' . $username,
                'sdf_lastupdated' => date('Y-m-d'),
                'agreedcontracts_description' => '<br>' . date('F j, Y') . ' Agreed by Us.',
                'agreedcontracts' => date('Y-m-d')
            ]);

            // New account's useroption → prime/rebuild its cache (Phase 2).
            app(\App\Services\TableCache::class)->rebuildUseroption($newuserid);

            // Insert affiliate invite
            DB::table('affiliateinvite')->insert([
                'assigned_userref' => $newuserid,
                'icode' => $newinvitecode,
                'codenote' => 'first code for new client created in Campaign Manager',
                'subdomain' => $newuserid // Use userref as unique subdomain
            ]);

            // Determine settings based on master account
            $userSettings = $this->getSubAccountSettings($username);

            // Insert new user
            DB::table('users')->insert([
                'bigid' => $newuserid,
                'uname' => $newusr,
                'pword' => $newpwd,
                'first_ip' => $signip,
                'contactname' => urlencode($newcontactname),
                'busname' => urlencode($newbusname),
                'contactemail' => $newtheemail,
                'phone' => $newthephone,
                'mobilenumber' => $newthemobile,
                'smsg_wallet' => $userSettings['smsg_wallet'],
                'bulk_throughput' => $userSettings['bulk_throughput'],
                'clientcommstatus' => 'cool',
                'affiliateinvitecode' => '',
                'user_type' => $userSettings['user_type'],
                'datejoined' => time(),
                'datefrozen' => time() + 31536000, // 1 year from now
                'bit_disabled' => 0,
                'premium_throughput' => 0,
                'daemonpriority' => $userSettings['daemonpriority'],
                'masteruname' => $username,
                'isnfcuser' => 'n',
                'nfckey' => $nfckey,
                'platinumaccess' => 'y',
                'chargetype1' => $userSettings['chargetype1'],
                'routetag' => $userSettings['routetag'],
                '1s_preferredroute' => $userSettings['preferredroute'],
                'dashboardaccess' => $userSettings['dashboardaccess'],
                'itaggsecretkey' => $newsecretkey,
                'role' => 'customer',
                'login_type' => 'customer',
                'migration_flag' => 'new',
            ]);

            // Insert user routes
            $this->insertUserRoutes($newuserid, $userSettings['userprice']);

            Log::info('New sub-account created', [
                'master_user' => $username,
                'new_user' => $newusr,
                'new_bigid' => $newuserid,
                'contactname' => $newcontactname,
                'busname' => $newbusname,
                'ip' => $signip
            ]);

            return redirect()->route('campaign.accounts.add')
                ->with('success', "Account has been successfully added!")
                ->with('new_account', [
                    'username' => $newusr,
                    'password' => $newpwd
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to create sub-account', [
                'master_user' => $username,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Failed to create account. Please try again.')
                ->withInput();
        }
    }

    /**
     * Generate unique NFC key
     */
    private function generateUniqueNfcKey()
    {
        do {
            $nfckey = $this->generateUniqueId(6, 'chars');
            $exists = DB::table('users')->where('nfckey', $nfckey)->exists();
        } while ($exists);

        return $nfckey;
    }

    /**
     * Generate unique invite code
     */
    private function generateUniqueInviteCode()
    {
        do {
            $code = strtoupper($this->generateUniqueId(5));
            $exists = DB::table('affiliateinvite')->where('icode', $code)->exists();
        } while ($exists);

        return $code;
    }

    /**
     * Generate unique ID
     */
    private function generateUniqueId($length = 32, $restrict = 'all')
    {
        if ($restrict == 'chars') {
            $allow = 'abcdefghjkmnpqrstvwxyz';
        } elseif ($restrict == 'nums') {
            $allow = '23456789';
        } else {
            $allow = 'abcdefghjkmnpqrstvwxyz23456789';
        }

        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $allow[random_int(0, strlen($allow) - 1)];
        }

        return $id;
    }

    /**
     * Get sub-account settings based on master account
     */
    private function getSubAccountSettings($masterUsername)
    {
        // Default settings for new sub-accounts
        $defaultSettings = [
            'smsg_wallet' => 0,
            'bulk_throughput' => 10000,
            'user_type' => 'freekey',
            'daemonpriority' => 400,
            'chargetype1' => 'pps',
            'routetag' => 'd',
            'preferredroute' => 7002,
            'dashboardaccess' => 'mc',
            'userprice' => '0.03'
        ];

        // Custom settings based on master account (similar to old PHP)
        $customSettings = [
            '5f13a718' => [ // Houshang
                'smsg_wallet' => 1.8,
                'bulk_throughput' => 50000,
                'userprice' => '0.018'
            ],
            '5f9fc8g5' => [ // Ali Komasi
                'bulk_throughput' => 1000,
                'userprice' => '0.018'
            ],
            '87a1e803' => [ // Simon Verona
                'userprice' => '0.0225'
            ],
            'a4e2cca2' => [ // Veezu
                'bulk_throughput' => 100000,
                'daemonpriority' => 500,
                'routetag' => 'p',
                'userprice' => '0.015'
            ],
            // Add more custom settings as needed
        ];

        // Check if master has custom settings
        if (isset($customSettings[$masterUsername])) {
            return array_merge($defaultSettings, $customSettings[$masterUsername]);
        }

        return $defaultSettings;
    }

    /**
     * Insert user routes for new account
     */
    private function insertUserRoutes($userref, $userprice)
    {
        $routes = [
            ['routenum' => 7002, 'numbits' => 7, 'origtype' => 'alpha'],
            ['routenum' => 7002, 'numbits' => 7, 'origtype' => 'msisdn'],
            ['routenum' => 7002, 'numbits' => 7, 'origtype' => 'shortcode'],
            ['routenum' => 7002, 'numbits' => 8, 'origtype' => 'alpha'],
            ['routenum' => 7002, 'numbits' => 8, 'origtype' => 'msisdn'],
            ['routenum' => 7002, 'numbits' => 8, 'origtype' => 'shortcode'],
            ['routenum' => 7029, 'numbits' => 7, 'origtype' => 'alpha'],
            ['routenum' => 7029, 'numbits' => 7, 'origtype' => 'msisdn'],
            ['routenum' => 7029, 'numbits' => 7, 'origtype' => 'shortcode'],
            ['routenum' => 7029, 'numbits' => 8, 'origtype' => 'alpha'],
            ['routenum' => 7029, 'numbits' => 8, 'origtype' => 'msisdn'],
            ['routenum' => 7029, 'numbits' => 8, 'origtype' => 'shortcode'],
        ];

        foreach ($routes as $route) {
            DB::table('smsg_userroute')->insert([
                'userref' => $userref,
                'username' => 'special rate users',
                'routenum' => $route['routenum'],
                'countrydialcode' => '44',
                'numbits' => $route['numbits'],
                'origtype' => $route['origtype'],
                'userprice' => $userprice,
                'priority' => 1
            ]);
        }
    }

    /**
     * Transfer wallet funds between accounts
     */
    public function transferWallet(Request $request)
    {
        $user = Session::get('user_info');
        $username = $user['username'] ?? '';
        $password = $user['password'] ?? '';

        $xferfrom = $request->input('xferfrom');
        $xferto = $request->input('xferto');
        $xferamount = floatval($request->input('xferamount', 0));

        if ($xferfrom == $xferto) {
            return redirect()->back()->with('error', 'Destination account must differ from originating account.');
        }

        if (empty($xferfrom) || empty($xferto)) {
            return redirect()->back()->with('error', 'Please specify both originating and destination accounts.');
        }

        if ($xferamount <= 0) {
            return redirect()->back()->with('error', 'Please specify a valid amount to transfer.');
        }

        // Verify accounts belong to user
        $fromAccount = User::where('uname', $xferfrom)->where('masteruname', $username)->first();
        $toAccount = User::where('uname', $xferto)->where('masteruname', $username)->first();

        // Also check if the master account is the from/to
        if (!$fromAccount && $xferfrom == $username) {
            $fromAccount = User::where('uname', $username)->first();
        }
        if (!$toAccount && $xferto == $username) {
            $toAccount = User::where('uname', $username)->first();
        }

        if (!$fromAccount || !$toAccount) {
            return redirect()->back()->with('error', 'Invalid account username.');
        }

        $fromWallet = $fromAccount->smsg_wallet - $fromAccount->smsg_server1_sent - $fromAccount->smsg_server2_sent;
        if ($fromWallet < $xferamount) {
            return redirect()->back()->with('error', 'Originating account does not have enough funds.');
        }

        // Perform transfer
        DB::table('users')->where('uname', $xferfrom)->decrement('smsg_wallet', $xferamount);
        DB::table('users')->where('uname', $xferto)->increment('smsg_wallet', $xferamount);

        // Log the transfer
        DB::table('money_transfer_logs')->insert([
            'ip_address' => $request->ip(),
            'from_account' => $xferfrom,
            'to_account' => $xferto,
            'created_by' => $username,
            'created' => now(),
            'amount' => $xferamount
        ]);

        $fromBusname = urldecode($fromAccount->busname);
        $toBusname = urldecode($toAccount->busname);

        return redirect()->back()->with('success', "£" . number_format($xferamount, 3) . " transferred from {$fromBusname} ({$xferfrom}) to {$toBusname} ({$xferto})");
    }

    /**
     * Download sample campaign CSV
     */
    public function downloadSampleCsv()
    {
        $filepath = public_path('campaign/sample_campaign.csv');

        if (file_exists($filepath)) {
            return response()->download($filepath, 'sample_campaign.csv');
        }

        // Create sample CSV if doesn't exist
        $content = "mobile,custom1,originator,message,sendtime,dlr_url,custom2,route\n";
        $content .= "447123456789,,YourBrand,Hello {name} this is a test message!,,,\n";
        $content .= "447987654321,,YourBrand,Special offer just for you!,,,\n";

        return response($content)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="sample_campaign.csv"');
    }

    /**
     * Download sample campaign Excel file
     */
    public function downloadSampleExcel()
    {
        $filepath = public_path('campaign/sample_campaign.xlsx');

        if (file_exists($filepath)) {
            return response()->download($filepath, 'sample_campaign.xlsx');
        }

        return redirect()->back()->with('error', 'Sample Excel file not found.');
    }

    /**
     * Download campaign instructions document
     */
    public function downloadInstructions()
    {
        $filepath = public_path('campaign/submit_new_sms_campaign_instructions.docx');

        if (file_exists($filepath)) {
            return response()->download($filepath, 'submit_new_sms_campaign_instructions.docx');
        }

        return redirect()->back()->with('error', 'Instructions document not found.');
    }

    /**
     * Main dashboard redirect
     */
    public function mainDashboard()
    {
        // Redirect to main dashboard
        return redirect('/dashboard');
    }

    /**
     * Download short URL report
     */
    public function downloadShortUrlReport(Request $request)
    {
        $user = Session::get('user_info');
        $userref = $user['bigid'] ?? null;
        $campaignref = $request->input('campaignref');

        if (!$userref || !$campaignref) {
            return response('Invalid request', 400);
        }

        $campaign = SmsgCampaign::where('userref', $userref)
            ->where('campaignid', $campaignref)
            ->first();

        if (!$campaign) {
            return response('Campaign not found', 404);
        }

        $filename = "iTagg_shorturl_report_{$campaignref}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($userref, $campaignref, $campaign) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'mobile number',
                'originator',
                'date created',
                'short domain',
                'short id',
                'destination url',
                'first click date',
                'first click ip address',
                'number of clicks',
                'campaign id',
                'campaign name',
                'first useragent'
            ]);

            $shorturls = DB::table('smsg_shorturl_forwarding')
                ->where('campaignref', $campaignref)
                ->orderBy('campaignrownum')
                ->get();

            $numrows = 0;
            $totclicks = 0;
            $totuniqueclicks = 0;

            foreach ($shorturls as $url) {
                if ($url->userref != $userref) {
                    continue; // Security check
                }

                $datecreatedtime = date('jS M Y, g:ia', strtotime($url->datecreated));
                $firstclickdatetime = '';
                if (!empty($url->firstclickdate) && $url->firstclickdate != '00000000000000') {
                    $firstclickdatetime = date('jS M Y, g:ia', strtotime($url->firstclickdate));
                }

                fputcsv($file, [
                    $url->mobnum,
                    urldecode($url->senderid ?? ''),
                    $datecreatedtime,
                    urldecode($url->shortdomain ?? ''),
                    $url->shortid,
                    urldecode($url->longurl ?? ''),
                    $firstclickdatetime,
                    $url->firstclickip,
                    $url->numclicks,
                    $campaignref,
                    $campaign->campaignname,
                    $url->firstuseragent ?? ''
                ]);

                $numrows++;
                $totclicks += $url->numclicks;
                if ($url->numclicks > 0) {
                    $totuniqueclicks++;
                }
            }

            // Summary rows
            fputcsv($file, []);
            fputcsv($file, ['', '', '', '', '', '', '', 'numbers in campaign:', $numrows, $campaignref, $campaign->campaignname]);
            fputcsv($file, ['', '', '', '', '', '', '', 'total clicks:', $totclicks, $campaignref, $campaign->campaignname]);
            fputcsv($file, ['', '', '', '', '', '', '', 'total unique clicks:', $totuniqueclicks, $campaignref, $campaign->campaignname]);

            if ($totuniqueclicks > 0 && $numrows > 0) {
                $percentage = sprintf('%.2f', ($totuniqueclicks / ($numrows / 100))) . '%';
                fputcsv($file, ['', '', '', '', '', '', '', 'percentage of links clicked:', $percentage, $campaignref, $campaign->campaignname]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

   /**
     * Step 1: Generate token and redirect to the other domain
     */
    public function generateTokenAndRedirectCampaign($username)
    {
        $user = User::where('uname', $username)->first();

        if (!$user) {
            return redirect()->back()->withErrors(['error' => 'User not found']);
        }

        // Always generate a new token for security
        $user->remember_token = Str::random(60);
        $user->save();
        $user_login_type = 'campaign';

        $customerDomains = config('domains.customer_domains');
        $campaignDomains    = config('domains.campaign_domains');
        $scheme = request()->getScheme();

        if ($user_login_type === 'campaign' && !empty($campaignDomains)) {
            $autologinUrl = $scheme . '://' . $customerDomains[0] . '/autologindash?token=' . $user->remember_token;
        } else {
            return redirect()->back()->withErrors(['error' => 'No domain configured.']);
        }


        return redirect()->away($autologinUrl);
    }

    /**
     * Step 2: Auto login on target domain
     */
    public function loginDashboardToken(Request $request)
    {
        $host = $request->getHost();

        $customerDomains = config('domains.customer');
        $campaignDomains    = config('domains.campaign');

        // 🔹 Set different session cookies dynamically
        if (in_array($host, $campaignDomains)) {
            Config::set('session.cookie', 'campaign_session');
        } elseif (in_array($host, $customerDomains)) {
            Config::set('session.cookie', 'customer_session');
        }

        $token = $request->query('token');
        if (!$token) {
            return redirect('/')->withErrors(['error' => 'Token missing']);
        }

        $user = User::where('remember_token', $token)->first();
        if (!$user) {
            return redirect('/')->withErrors(['error' => 'Invalid or expired token']);
        }

        if ($user->bit_disabled == 1) {
            return redirect('/')->with('error', 'Account disabled.');
        }

        // Store user info in session
        Session::put('user_info', [
            'contactname' => $user->contactname,
            'bigid'       => $user->bigid,
            'username'    => $user->uname,
            'login_type'  => $user->login_type,
        ]);

        Auth::login($user);

        // Check lockout
        $lockoutStatus = DB::table('useroption')
            ->where('userref', $user->bigid)
            ->select('profileupdate_lockout', 'clientcommfail')
            ->first();

        if ($lockoutStatus && $lockoutStatus->profileupdate_lockout == '1') {
            return redirect()->route('profile.lock');
        }

        if ($lockoutStatus && $lockoutStatus->clientcommfail == 'y') {
            return redirect('/')->with('error', 'Account locked.');
        }

        return redirect('/dashboard');
    }
}
