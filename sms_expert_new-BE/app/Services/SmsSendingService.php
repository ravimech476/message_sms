<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Queue\SmsQueueService;
use App\Services\BulkThroughputService;
use App\Services\WalletValidationService;
use App\Services\UserRouteService;
use App\Services\Logging\ApiLogService;
use App\Services\CustomerFeatureService;
use App\Services\SmppProxyAuthService;
use App\Helpers\GsmCharacterConverter;
use Carbon\Carbon;
use Exception;

/**
 * SMS Sending Service
 * 
 * Common service for sending SMS messages via API and Dashboard.
 * Maintains compatibility with legacy API response format.
 * 
 * Legacy API URL: https://secure.devsmsexpert.com/smsg/sms.mes?usr=XXX&pwd=YYY&from=myname&to=447912345678,447987654321&type=text&route=d&txt=helloworld
 * 
 * Response Format:
 * error code|error text|submission reference
 * 0|sms submitted|bigid-servernum
 */
class SmsSendingService
{
    protected $smsQueueService;
    protected $bulkThroughputService;
    protected $walletValidationService;
    protected $customerFeatureService;
    protected $smppProxyAuthService;

    // Server base batch number (for legacy compatibility)
    // OLD SYSTEM parity: the API submission-reference suffix is `$bigid-$thisserverbasebatchnums`
    // (smssend.inc:1140). In the OLD SYSTEM this is a PER-SERVER value — platforme.inc detects
    // which box it runs on (from the /home/sites/siteN path) and looks up that server's number
    // (live site7 "Elephant" = 3, others 0/1/2). It is the server identifier, NOT the recipient
    // count. The modern equivalent of a per-server constant is an .env value, so each deployment
    // sets SERVER_BASE_BATCH_NUM. Default 3 = the live server's value. Assigned in the constructor.
    protected $serverBaseBatchNum = '3';

    // Debug mode execution start time
    protected $debugStartTime;

    // Initiator constants (matching legacy system)
    const SENDER_API = 'API';
    const SENDER_CONTROL_PANEL = 'ControlPanel';
    const SENDER_EMAIL_API = 'EmailAPI';
    const SENDER_ITAGG = 'iTAGG';

    // Error codes (matching legacy system)
    const ERROR_OK = 0;
    const ERROR_BAD_USER = 1;
    const ERROR_MISSING_FIELDS = 2;
    const ERROR_INVALID_ROUTE = 3;
    const ERROR_INSUFFICIENT_CREDIT = 102;
    const ERROR_SUBMISSION_FAILED = 100;
    const ERROR_THROUGHPUT_EXCEEDED = 302;
    const ERROR_SECRET_KEY_INVALID = 460;
    const ERROR_BLACKLISTED = 600;

    public function __construct()
    {
        $this->bulkThroughputService = new BulkThroughputService();
        $this->walletValidationService = new WalletValidationService();
        $this->customerFeatureService = new CustomerFeatureService();
        $this->smppProxyAuthService = new SmppProxyAuthService();

        // Per-server submission-reference suffix (OLD SYSTEM $thisserverbasebatchnums).
        // Each deployment sets SERVER_BASE_BATCH_NUM in .env, mirroring the OLD SYSTEM's
        // per-server value. Falls back to 3 (the live server's number) when unset.
        $this->serverBaseBatchNum = (string) env('SERVER_BASE_BATCH_NUM', $this->serverBaseBatchNum);

        try {
            $this->smsQueueService = new SmsQueueService();
        } catch (Exception $e) {
            Log::warning('SMS Queue Service not available: ' . $e->getMessage());
            $this->smsQueueService = null;
        }
    }

    /**
     * Send SMS - Main entry point for both API and Dashboard
     * 
     * @param array $params Parameters for sending SMS
     *   - usr: Username (required if no userbigid)
     *   - pwd: Password (required if no userbigid)
     *   - userbigid: User's bigid (alternative to usr/pwd)
     *   - itaggsecretkey: Secret key (alternative auth)
     *   - from: Sender ID
     *   - to: Recipients (comma-separated)
     *   - txt: Message text
     *   - type: Message type (text, binary, unicode, longmessage)
     *   - route: Route identifier (d, p, e, s, g, l, or numeric)
     *   - send: Send time (YmdHis format) or empty for immediate
     *   - initiator: Who initiated the send
     *   - dreceipt_url: Delivery receipt URL (optional)
     *   - userdefined: User-defined data (optional)
     *   - campaignref: Campaign reference (optional)
     * 
     * @return array Result with 'success', 'response', 'bigid', etc.
     */
    public function sendSms(array $params): array
    {
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $datenowDisplay = Carbon::now('Europe/London')->format('H:i.s - D jS F Y');
        $daynow = Carbon::now('Europe/London')->format('Ymd');

        // Initialize default values
        $params = array_merge([
            'usr' => '',
            'pwd' => '',
            'userbigid' => '',
            'itaggsecretkey' => '',
            'smppusr' => '',  // SMPP proxy user parameter (OLD SYSTEM: Craig Rutherford)
            'subusrkey' => '',  // OLD SYSTEM: pooled virtual-number sub-account key
            'from' => env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME'),
            'to' => '',
            'txt' => '',
            'type' => 'text',
            'route' => 'd',
            'send' => '',
            'initiator' => self::SENDER_API,
            'dreceipt_url' => '',
            'userdefined' => '',
            'campaignref' => '',
            'incominglog_ref' => 0,
            'sitype' => '',
            'userdefinedSubRef' => '',
            'binaryflags' => '',  // OLD SYSTEM: binary message flags
            'incoming_message_id' => '0',  // OLD SYSTEM: link outbound message to inbound MO
            'msisdnAlias' => '',  // OLD SYSTEM: alias -> msisdn lookup (location forwarding)
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,  // Client IP for SMPP proxy validation
            // Every send through this service belongs to the NEW system, so smsg_log rows
            // default to 'new' — required for the new DLR/delivery/reporting pipeline to
            // process them (it filters migration_flag = 'new'). Callers that omit it
            // (sendSi/sendSmsJson) previously inserted NULL and got orphaned.
            'migration_flag' => 'new',
        ], $params);

        // Log the request
        $this->logRequest($params, $datenowDisplay);

        // Authenticate user
        $authResult = $this->authenticateUser($params);
        if (!$authResult['success']) {
            // Log failed auth to anonymous
            $apiLog = ApiLogService::for('auth_failed');
            $apiLog->warning('Authentication failed', [
                'usr' => $params['usr'] ?? 'N/A',
                'error_code' => $authResult['error_code'],
                'error_text' => $authResult['error_text'],
            ]);
            return $this->formatResponse($authResult['error_code'], $authResult['error_text']);
        }

        $user = $authResult['user'];
        $userref = $user['bigid'];

        // Check if debug mode is enabled for this customer (OLD SYSTEM compatibility)
        $isDebugMode = $this->customerFeatureService->isDebugMode($userref);
        if ($isDebugMode) {
            $this->debugStartTime = microtime(true);
        }

        // Check if test mode is enabled (skip actual SMS sending)
        $isTestMode = $this->customerFeatureService->isTestMode($userref);

        // Apply route fix if configured for this customer (OLD SYSTEM: Brillchris route 8->7)
        $routeFixResult = $this->customerFeatureService->applyRouteFix($userref, $params['route']);
        if ($routeFixResult['fixed']) {
            Log::info('Route fix applied for customer', [
                'userref' => $userref,
                'original_route' => $params['route'],
                'new_route' => $routeFixResult['route'],
            ]);
            $params['route'] = $routeFixResult['route'];
        }

        // Initialize API logger for this customer
        $apiLog = ApiLogService::for($userref);
        $apiLog->logSmsSubmission([
            'from' => $params['from'],
            'to' => $params['to'],
            'type' => $params['type'],
            'route' => $params['route'],
            'usr' => $params['usr'],
        ]);

        // Validate required parameters
        $validationResult = $this->validateParams($params, $user);
        if (!$validationResult['success']) {
            $apiLog->logSmsResult('', $validationResult['error_code'], $validationResult['error_text']);
            return $this->formatResponse($validationResult['error_code'], $validationResult['error_text']);
        }

        // Check bulk throughput limit
        $throughputCheck = $this->bulkThroughputService->checkAndUpdateThroughput($userref);
        if (!$throughputCheck['allowed']) {
            $apiLog->logSmsResult('', self::ERROR_THROUGHPUT_EXCEEDED, 'throughput exceeded');
            return $this->formatResponse(self::ERROR_THROUGHPUT_EXCEEDED, 'throughput exceeded');
        }

        // OLD SYSTEM: msisdn alias (location forwarding). Resolve alias -> msisdn,
        // overwrite `to`, and tag userdefined so the real msisdn isn't shown. If the
        // alias can't be found, fail exactly like sms.mes (301|Invalid msisdn alias).
        if (!empty($params['msisdnAlias'])) {
            $aliasMsisdn = DB::table('itagg_msisdn_alias')
                ->join('users', 'itagg_msisdn_alias.users_bigid', '=', 'users.bigid')
                ->where('itagg_msisdn_alias.alias', $params['msisdnAlias'])
                ->where('users.bigid', $userref)
                ->value('itagg_msisdn_alias.msisdn');
            if (empty($aliasMsisdn)) {
                return $this->formatResponse(301, 'Invalid msisdn alias');
            }
            $params['to'] = $aliasMsisdn;
            $tag = 'MSISDNALIAS=' . $params['msisdnAlias'];
            $params['userdefined'] = ($params['userdefined'] === '' || $params['userdefined'] === null)
                ? $tag
                : $params['userdefined'] . '__USERDEFINED_DATA_BOUNDARY__' . $tag;
        }

        // OLD SYSTEM: incoming_message_id -> resolve to itagg_incominglog.id so the
        // outbound message is linked to the inbound MO request (stored as incominglog_ref).
        if (!empty($params['incoming_message_id']) && $params['incoming_message_id'] !== '0') {
            $incId = DB::table('itagg_incominglog')
                ->where('operator_message_id', $params['incoming_message_id'])
                ->value('id');
            if (!empty($incId)) {
                $params['incominglog_ref'] = $incId;
            }
        }

        // Parse and validate phone numbers
        $numbersResult = $this->parseAndValidateNumbers($params['to']);
        if (!$numbersResult['success']) {
            $apiLog->logSmsResult('', $numbersResult['error_code'], $numbersResult['error_text']);
            return $this->formatResponse($numbersResult['error_code'], $numbersResult['error_text']);
        }

        $phoneNumbers = $numbersResult['numbers'];
        $countryCodes = $numbersResult['country_codes'];

        // Check for blacklisted numbers. Only the absolute hardcoded list rejects here (600).
        // Per-user (STOP) blacklisted numbers stay in the send list and are logged as failed
        // rows by insertAndQueueMessages() — matching OLD SYSTEM (see checkBlacklist docblock).
        $blacklistResult = $this->checkBlacklist($phoneNumbers, $userref);
        if (!$blacklistResult['success']) {
            $apiLog->logSmsResult('', self::ERROR_BLACKLISTED, $blacklistResult['error_text']);
            return $this->formatResponse(self::ERROR_BLACKLISTED, $blacklistResult['error_text']);
        }
        $phoneNumbers = $blacklistResult['allowed_numbers'];
        $userBlacklist = $blacklistResult['user_blacklist'];

        if (empty($phoneNumbers)) {
            $apiLog->logSmsResult('', self::ERROR_BLACKLISTED, 'all numbers are blacklisted');
            return $this->formatResponse(self::ERROR_BLACKLISTED, 'all numbers are blacklisted');
        }

        // Check wallet balance
        $walletCheck = $this->walletValidationService->validateWalletBalance($userref, count($phoneNumbers), $phoneNumbers);
        if (!$walletCheck['has_funds']) {
            $apiLog->logSmsResult('', self::ERROR_INSUFFICIENT_CREDIT, 'submission failed due to insufficient credit');
            return $this->formatResponse(self::ERROR_INSUFFICIENT_CREDIT, 'submission failed due to insufficient credit');
        }

        // Generate bigid for this batch
        $bigid = $this->generateBigId($params['userdefinedSubRef']);
        if (isset($bigid['error'])) {
            $apiLog->logSmsResult('', self::ERROR_MISSING_FIELDS, $bigid['error']);
            return $this->formatResponse(self::ERROR_MISSING_FIELDS, $bigid['error']);
        }

        // Process and clean the message text
        $messageResult = $this->processMessageText($params['txt'], $params['type'], $user);
        if (!$messageResult['success']) {
            return $this->formatResponse($messageResult['error_code'], $messageResult['error_text']);
        }

        $txtToSave = $messageResult['txt_to_save'];
        $txtForLength = $messageResult['txt_for_length'];
        $numbits = $messageResult['numbits'];
        $numParts = $messageResult['num_parts'];

        // Get delivery receipt URL
        $dreceiptUrl = $this->getDreceiptUrl($params['dreceipt_url'], $userref);

        // Determine send time
        $sendTime = empty($params['send']) ? $datenow : $params['send'];
        $isScheduled = $sendTime > $datenow;

        // Determine route
        $routeNumber = $this->determineRoute($params['route'], $user);

        // Insert messages into database and queue for sending
        $insertResult = $this->insertAndQueueMessages([
            'bigid' => $bigid,
            'phone_numbers' => $phoneNumbers,
            'country_codes' => $countryCodes,
            'txt_to_save' => $txtToSave,
            'from' => $params['from'],
            'numbits' => $numbits,
            'num_parts' => $numParts,
            'datenow' => $datenow,
            'userref' => $userref,
            'affiliateref' => $user['affiliateref'] ?? '0',
            'send_time' => $sendTime,
            'is_scheduled' => $isScheduled,
            'initiator' => $params['initiator'],
            'route_number' => $routeNumber,
            'dreceipt_url' => $dreceiptUrl,
            'userdefined' => $params['userdefined'],
            'campaignref' => $params['campaignref'],
            'incominglog_ref' => $params['incominglog_ref'],
            'sitype' => $params['sitype'],
            'binaryflags' => $params['binaryflags'],
            'user' => $user,
            'migration_flag' => $params['migration_flag'],
            // Per-user (STOP) blacklisted numbers → logged as failed, never queued (OLD parity).
            'user_blacklist' => $userBlacklist,

        ]);

        if (!$insertResult['success']) {
            $apiLog->logSmsResult($bigid, self::ERROR_SUBMISSION_FAILED, 'submission failed');
            return $this->formatResponse(self::ERROR_SUBMISSION_FAILED, 'submission failed');
        }

        // Log success result
        $apiLog->logSmsResult(
            $bigid,
            self::ERROR_OK,
            'sms submitted',
            $insertResult['success_count'],
            $insertResult['failed_count']
        );

        // Build extra response data
        $extraData = [
            'bigid' => $bigid,
            'queued' => $insertResult['success_count'],
            'failed' => $insertResult['failed_count'],
            'blacklisted' => $insertResult['blacklisted_count'] ?? 0,
            'total' => count($phoneNumbers),
        ];

        // OLD SYSTEM compatibility: Add debug info if debug mode is enabled
        // (Steve's account showed execution time)
        if ($isDebugMode && $this->debugStartTime) {
            $executionTime = round((microtime(true) - $this->debugStartTime) * 1000, 2);
            $extraData['debug'] = [
                'execution_time_ms' => $executionTime,
                'route_fix_applied' => $routeFixResult['fixed'] ?? false,
                'test_mode' => $isTestMode,
            ];
            Log::debug('Debug mode execution info', $extraData['debug']);
        }

        // Return success response in legacy format
        return $this->formatResponse(
            self::ERROR_OK,
            'sms submitted',
            $bigid . '-' . $this->serverBaseBatchNum,
            $extraData
        );
    }

    /**
     * Authenticate user by username/password, bigid, secret key, or SMPP proxy
     *
     * OLD SYSTEM compatibility: Craig Rutherford SMPP proxy authentication
     * Craig provides an SMPP front end and authenticates with his credentials,
     * then passes smppusr param to represent the actual user.
     */
    protected function authenticateUser(array $params): array
    {
        $usr = trim($params['usr'] ?? '');
        $pwd = trim($params['pwd'] ?? '');
        $userbigid = trim($params['userbigid'] ?? '');
        $secretKey = trim($params['itaggsecretkey'] ?? '');
        $smppUsr = trim($params['smppusr'] ?? '');
        $clientIp = $params['client_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

        // Try secret key authentication first
        if (!empty($secretKey) && !empty($usr)) {
            $user = DB::table('users')
                ->where('itaggsecretkey', $secretKey)
                ->where('uname', $usr)
                ->first();

            if (!$user) {
                return ['success' => false, 'error_code' => self::ERROR_SECRET_KEY_INVALID, 'error_text' => 'bad user details'];
            }

            return ['success' => true, 'user' => (array)$user];
        }

        // Try username/password authentication
        if (!empty($usr) && !empty($pwd)) {
            // OLD SYSTEM compatibility: Check for SMPP proxy authentication
            // Craig Rutherford (and other SMPP proxies) use their credentials
            // with smppusr param to authenticate on behalf of other users
            $proxyAuthResult = $this->smppProxyAuthService->authenticate(
                $usr,
                $pwd,
                $smppUsr,
                $clientIp
            );

            if ($proxyAuthResult['is_proxy']) {
                // This is an SMPP proxy request
                if ($proxyAuthResult['success']) {
                    Log::info('SMPP Proxy authentication successful', [
                        'proxy_user' => $proxyAuthResult['proxy_user'] ?? $usr,
                        'actual_user' => $smppUsr,
                        'ip' => $clientIp,
                    ]);
                    return [
                        'success' => true,
                        'user' => $proxyAuthResult['user'],
                        'via_proxy' => true,
                        'proxy_user' => $proxyAuthResult['proxy_user'] ?? $usr,
                    ];
                } else {
                    // Proxy auth failed - return specific error code
                    return [
                        'success' => false,
                        'error_code' => $proxyAuthResult['error_code'],
                        'error_text' => $proxyAuthResult['error_text'],
                    ];
                }
            }

            // Regular username/password authentication
            $user = DB::table('users')
                ->where('uname', $usr)
                ->where('pword', $pwd)
                ->first();

            if (!$user) {
                return ['success' => false, 'error_code' => self::ERROR_BAD_USER, 'error_text' => 'bad user details (1)'];
            }

            return ['success' => true, 'user' => (array)$user];
        }

        // Try bigid authentication
        if (!empty($userbigid)) {
            $user = DB::table('users')
                ->where('bigid', $userbigid)
                ->first();

            if (!$user) {
                return ['success' => false, 'error_code' => self::ERROR_BAD_USER, 'error_text' => 'bad user details(3)'];
            }

            return ['success' => true, 'user' => (array)$user];
        }

        return ['success' => false, 'error_code' => self::ERROR_BAD_USER, 'error_text' => 'bad user details(5)'];
    }

    /**
     * Validate required parameters
     */
    protected function validateParams(array $params, array $user): array
    {
        // Validate 'to' parameter
        if (empty($params['to']) || trim($params['to']) === '' || trim($params['to']) === '447999999999') {
            return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (to)'];
        }

        // Validate 'from' parameter
        if (empty($params['from']) || trim($params['from']) === '') {
            return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (from)'];
        }

        // Validate sender ID length for alphanumeric
        $from = $params['from'];
        if (!preg_match('/^[0-9]+$/', $from) && strlen($from) > 11) {
            return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (from too long)'];
        }

        // Validate 'type' parameter
        $validTypes = ['text', 'binary', 'unicode', 'longmessage'];
        if (empty($params['type']) || !in_array(strtolower($params['type']), $validTypes)) {
            return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (type is invalid)'];
        }

        // Validate 'txt' parameter
        if (empty($params['txt']) || trim($params['txt']) === '') {
            return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (1000)'];
        }

        // Validate message length based on type
        $txt = $params['txt'];
        $type = strtolower($params['type']);

        if ($type === 'text' || $type === 'longmessage') {
            if (strlen($txt) > 1377) {
                return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (1001)'];
            }
        } elseif ($type === 'binary') {
            if (strlen($txt) > 459) {
                return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (1002)'];
            }
        } elseif ($type === 'unicode') {
            if (strlen($txt) > 70) {
                return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (1003)'];
            }
        }

        // Validate route
        $route = $params['route'];
        if (empty($route)) {
            return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields (routenumber)'];
        }

        // Convert route 900-999 to standard routes
        if (is_numeric($route) && $route > 899 && $route < 1000) {
            $route = 7;
        }

        if (!$this->isValidRoute($route)) {
            return ['success' => false, 'error_code' => self::ERROR_INVALID_ROUTE, 'error_text' => 'invalid route'];
        }

        // Validate delivery receipt URL if provided
        if (!empty($params['dreceipt_url'])) {
            if (!preg_match('/^https?:\/\/[^\.\'\"]+\.[^\.\"\']+?[^\'\"]*$/', $params['dreceipt_url'])) {
                return ['success' => false, 'error_code' => self::ERROR_MISSING_FIELDS, 'error_text' => 'missing or invalid fields : invalid delivery receipt URL'];
            }
        }

        return ['success' => true];
    }

    /**
     * Check if route is valid
     */
    protected function isValidRoute($route): bool
    {
        // Valid route letters
        $validLetters = ['d', 'p', 'e', 's', 'g', 'l'];
        if (in_array(strtolower($route), $validLetters)) {
            return true;
        }

        // Valid numeric routes
        if (is_numeric($route)) {
            $routeNum = (int)$route;

            // Routes 1-500 are valid
            if ($routeNum > 0 && $routeNum < 501) {
                return true;
            }

            // Routes 900-999 are virtual routes (converted to real routes)
            if ($routeNum >= 900 && $routeNum <= 999) {
                return true;
            }

            // Specific valid standard routes
            $validStdRoutes = [3, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29];
            if (in_array($routeNum, $validStdRoutes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse and validate phone numbers
     */
    protected function parseAndValidateNumbers(string $to): array
    {
        // Clean up the numbers string
        $to = trim(str_replace([' ', ':', ';'], ['', ',', ','], $to));
        if (substr($to, -1) === ',') {
            $to = substr($to, 0, -1);
        }

        $numbers = explode(',', $to);
        $validNumbers = [];
        $countryCodes = [];

        foreach ($numbers as $number) {
            $number = trim($number);

            // Format UK numbers starting with 0
            if (substr($number, 0, 1) === '0') {
                $number = '44' . substr($number, 1);
            }

            // Clean up malformed UK numbers
            if (substr($number, 0, 6) === '440447') {
                $number = substr($number, 3);
            }
            if (substr($number, 0, 5) === '44447') {
                $number = substr($number, 2);
            }

            // Validate number format
            if (strlen($number) < 8 || strlen($number) > 15 || preg_match('/[^0-9]/', $number)) {
                return [
                    'success' => false,
                    'error_code' => self::ERROR_MISSING_FIELDS,
                    'error_text' => 'missing or invalid fields (to)'
                ];
            }

            // Get country code
            $countryCode = $this->extractCountryCode($number);
            if (!$countryCode) {
                return [
                    'success' => false,
                    'error_code' => self::ERROR_MISSING_FIELDS,
                    'error_text' => 'missing or invalid fields: unknown country'
                ];
            }

            $validNumbers[] = $number;
            $countryCodes[] = $countryCode;
        }

        return [
            'success' => true,
            'numbers' => $validNumbers,
            'country_codes' => $countryCodes
        ];
    }

    /**
     * Extract country code from phone number
     */
    protected function extractCountryCode(string $phoneNumber): ?string
    {
        // Served from the no-TTL country cache (TableCache) instead of a per-message DB query.
        // countryForNumber replicates the old longest-prefix ("LENGTH(dialcode) DESC") match.
        $country = app(\App\Services\TableCache::class)->countryForNumber($phoneNumber);

        return $country ? $country->dialcode : null;
    }

    /**
     * Check for blacklisted numbers.
     *
     * OLD SYSTEM has TWO distinct blacklist mechanisms and they behave differently:
     *
     *  1. Absolute hardcoded blacklist (smssend.inc:799-808) — numbers we must NEVER
     *     send to. Checked at API submit time; a single match ABORTS THE WHOLE submission
     *     with `600|globally blocked number (<num>)|0` and inserts NOTHING into smsg_log.
     *
     *  2. Per-user STOP opt-out (itagg_outbound_blacklist) — checked by the sending worker
     *     (smsg_2send_body.inc:3007-3016), NOT at API time. The row is still INSERTED and
     *     the API returns `0|sms submitted|<ref>`; the worker then marks the row
     *     'fail' / 'Blacklisted number.' and never sends it. (The pre-2011 "courtesy first
     *     message" allow-through was disabled 2011-06-09 — all sends are now blocked.)
     *
     * So we only reject (600) for mechanism #1. For #2 we keep the number in the send list
     * and return a per-number map so insertAndQueueMessages() can log a failed, un-queued
     * row that mirrors the OLD worker's UPDATE exactly.
     */
    protected function checkBlacklist(array $numbers, string $userref): array
    {
        // 1. Absolute hardcoded blacklist — any match aborts the whole submission (OLD parity).
        $globalBlacklist = ['447710101010', '447725903111'];
        foreach ($numbers as $number) {
            if (in_array($number, $globalBlacklist, true)) {
                return [
                    'success' => false,
                    'error_text' => 'globally blocked number (' . $number . ')',
                ];
            }
        }

        // 2. Per-user STOP opt-out — log-but-don't-send. Fetch date_blocked + stop_or_stopall
        //    so we can reproduce the OLD upstream_errormessage string verbatim.
        $userBlacklist = [];
        $rows = DB::table('itagg_outbound_blacklist')
            ->where('users_bigid', $userref)
            ->whereIn('msisdn', $numbers)
            ->get(['msisdn', 'date_blocked', 'stop_or_stopall']);

        foreach ($rows as $row) {
            $userBlacklist[$row->msisdn] = [
                'date_blocked'    => $row->date_blocked,
                'stop_or_stopall' => $row->stop_or_stopall,
            ];
        }

        // All numbers proceed to insert; blacklisted ones are logged as failed (not sent).
        return [
            'success' => true,
            'user_blacklist' => $userBlacklist,
            'allowed_numbers' => $numbers,
        ];
    }

    /**
     * Generate unique bigid for the message batch
     */
    protected function generateBigId(string $userDefinedSubRef = ''): string
    {
        // If user provided their own reference, validate and use it
        if (!empty($userDefinedSubRef)) {
            if (strlen($userDefinedSubRef) > 32) {
                return ['error' => 'Userdefined Submission Reference too long. 32 characters max.'];
            }
            if (strlen($userDefinedSubRef) < 16) {
                return ['error' => 'Userdefined Submission Reference too short. 16 characters min.'];
            }
            if (!preg_match('/^[0-9a-zA-Z]{16,32}$/', $userDefinedSubRef)) {
                return ['error' => 'Userdefined Submission Reference contains invalid characters'];
            }
            return $userDefinedSubRef;
        }

        // Generate unique bigid
        $attempts = 0;
        $maxAttempts = 10;

        do {
            $bigid = md5(uniqid(rand(), true));
            $exists = DB::table('smsg_log')->where('bigid', $bigid)->exists();
            $attempts++;
        } while ($exists && $attempts < $maxAttempts);

        return $bigid;
    }

    /**
     * Process message text (encoding, GSM conversion, etc.)
     *
     * OLD SYSTEM compatibility: Applies UTF-8 decode for specific customers
     * (Leadbyte, Papersky, DMLS, Meteor, etc.) for message length checking
     */
    protected function processMessageText(string $txt, string $type, array $user): array
    {
        $type = strtolower($type);
        $userref = $user['bigid'] ?? '';
        $masterUsername = $user['master_uname'] ?? null;

        // Determine numbits based on type
        if ($type === 'text' || $type === 'longmessage') {
            $numbits = 7;
        } elseif ($type === 'binary') {
            $numbits = 8;
        } elseif ($type === 'unicode') {
            $numbits = 16;
        } else {
            $numbits = 7;
        }

        // Handle line break conversion (|| to newline)
        if (strpos($txt, '||') !== false) {
            $txt = str_replace('||', chr(10), $txt);
        }

        $txtForLength = $txt;

        // OLD SYSTEM compatibility: Apply UTF-8 decode for specific customers
        // This was hardcoded in smssend.inc lines 832-844 for customers like
        // Leadbyte, Papersky, Ian Johnson, DMLS, Meteor, David Lynes, etc.
        if ($this->customerFeatureService->shouldUtf8Decode($userref, $masterUsername)) {
            $txtForLength = $this->customerFeatureService->processMessageForLengthCheck(
                $txt,
                $userref,
                $masterUsername
            );
            Log::debug('UTF-8 decode applied for message length check', [
                'userref' => $userref,
                'original_length' => strlen($txt),
                'decoded_length' => strlen($txtForLength),
            ]);
        }

        // For 7-bit text, apply GSM character conversion
        if ($numbits === 7) {
            // Convert Unicode characters to GSM equivalents
            try {
                $conversionResult = GsmCharacterConverter::convert($txt, false);
                $txt = $conversionResult['message'];
                // Only update txtForLength if UTF-8 decode wasn't applied
                if (!$this->customerFeatureService->shouldUtf8Decode($userref, $masterUsername)) {
                    $txtForLength = $txt;
                }

                if (!empty($conversionResult['converted'])) {
                    Log::info('GSM Character Conversion applied', [
                        'original_encoding' => $conversionResult['original_encoding'],
                        'converted_encoding' => $conversionResult['converted_encoding'],
                        'characters_converted' => count($conversionResult['converted'])
                    ]);
                }
            } catch (Exception $e) {
                Log::warning('GSM conversion failed: ' . $e->getMessage());
            }

            // Legacy character replacements for non-UTF8 clients
            $txtToSave = urlencode($txt);
            $txtToSave = str_replace(
                ['%C2', '%92', '%96', '%09', '%EA', '%7C', '%7B', '%7D', '%7E', '%60', '%5B', '%5D'],
                ['', '%27', '-', '+', 'e', '%21', '%28', '%29', '-', '%27', '%28', '%29'],
                $txtToSave
            );
        } else {
            $txtToSave = urlencode($txt);
        }

        // Calculate number of SMS parts
        if (strlen($txtForLength) > 160) {
            $numParts = ceil(strlen($txtForLength) / 153);
        } else {
            $numParts = 1;
        }

        return [
            'success' => true,
            'txt_to_save' => $txtToSave,
            'txt_for_length' => $txtForLength,
            'numbits' => $numbits,
            'num_parts' => $numParts
        ];
    }

    /**
     * Resolve the delivery-receipt (DLR) callback URL for a message.
     *
     * OLD SYSTEM parity (smssend.inc:951-963 + getDefaultDreceiptURL): if the caller
     * supplied a per-message URL, use it; otherwise fall back to the account default
     * stored in useroption.dreceipt_push_url. Every OLD send path (API AND control panel)
     * ran through this, which is why control-panel sends still fired callbacks. The value
     * is stored on smsg_log.dreceipt_url so the DLR processor knows where to push.
     *
     * Public so the dashboard/control-panel path (SMSController) can resolve it the same
     * way the API path does — single source of truth.
     */
    public function getDreceiptUrl(string $providedUrl, string $userref): string
    {
        if (!empty($providedUrl)) {
            return $providedUrl;
        }

        // Get default from the cached useroption row (Phase 2) instead of a per-send query.
        $userOption = app(\App\Services\TableCache::class)->useroption($userref);

        return $userOption->dreceipt_push_url ?? '';
    }

    /**
     * Determine the route number
     *
     * OLD SYSTEM compatibility: Route override capability for test accounts
     * (Steve's test accounts could use special routes)
     */
    protected function determineRoute($route, array $user): int
    {
        $userref = $user['bigid'] ?? '';

        // OLD SYSTEM parity: the real routenum used for pricing/sending is the user's
        // PREFERRED route (users.1s_preferredroute / preferredroute2 / preferredroute3),
        // NOT the raw number a client passes. A generic/low value (<50) is normalised up
        // to 7002 — the canonical UK direct route — exactly like the old send daemon's
        // guard `if ($requested_route < 50) {$requested_route = 7002;}`
        // (smsg_2send_body.inc:1127). Without this, a low route like 7 has no
        // smsg_userroute rate row and pricing falls back to the hard-coded 5p default.
        $normalisePreferred = static function ($val): int {
            $r = (int) $val;
            return ($r >= 50) ? $r : 7002;
        };

        $preferred  = $normalisePreferred($user['1s_preferredroute'] ?? 0);
        $preferred2 = $normalisePreferred($user['preferredroute2'] ?? 0);
        $preferred3 = $normalisePreferred($user['preferredroute3'] ?? 0);

        // Letter routes select which of the user's preferred routes to use
        // (OLD SYSTEM smssend.inc::decidewhichroute).
        $routeMapping = [
            'd' => $preferred,
            'p' => $preferred2,
            'e' => $preferred3,
            's' => $preferred,
            'g' => $preferred,
            'l' => $preferred,
        ];

        if (isset($routeMapping[strtolower($route)])) {
            return $routeMapping[strtolower($route)];
        }

        // Numeric route
        if (is_numeric($route)) {
            $routeNum = (int)$route;

            // OLD SYSTEM compatibility: Check if customer has route override capability
            // (Steve's test accounts could use special routes like 900-999)
            if ($routeNum > 899 && $routeNum < 1000) {
                if ($this->customerFeatureService->hasRouteOverride($userref)) {
                    Log::debug('Route override applied for test account', [
                        'userref' => $userref,
                        'original_route' => $routeNum,
                    ]);
                    // Allow the special route for override accounts
                    return $routeNum;
                }
                // Non-override account: treat as generic -> user's preferred route
                return $preferred;
            }

            // OLD SYSTEM parity (smssend.inc::decidewhichroute else-branch +
            // smsg_2send_body.inc:1127): a generic low route number (e.g. 7, 8, 3) is
            // NOT a real routenum — it only selects the user's route. Resolve it to the
            // user's preferred route so pricing finds the account's real smsg_userroute
            // rate (e.g. route 7 -> 7002 -> the true 2.91p rate) instead of the 5p default.
            if ($routeNum > 0 && $routeNum < 50) {
                return $preferred;
            }

            // Real route numbers (7002, 8002, 3002, 51-500, …) pass through unchanged.
            return $routeNum;
        }

        // Default route
        return $preferred;
    }

    // Load Test Queue works changes(working functions in below)
    // protected function insertAndQueueMessages_old(array $params): array
    // {
    //     $successCount = 0;
    //     $failedCount  = 0;

    //     $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

    //     $phoneNumbers = $params['phone_numbers'];
    //     $countryCodes = $params['country_codes'];

    //     // whitelist numbers
    //     $allowedQueueNumbers = array_map(
    //         'trim',
    //         explode(',', env('QUEUE_TEST_NUMBERS', ''))
    //     );

    //     $totalNumbers = count($phoneNumbers); // small optimization

    //     foreach ($phoneNumbers as $i => $thenum) {

    //         try {

    //             $countryCode = $countryCodes[$i] ?? '44';

    //             /* -------------------------
    //            Status
    //         --------------------------*/
    //             if ($params['is_scheduled']) {
    //                 $sentstatus = 'tomorrowonward';
    //                 $sentstatustext = 'Scheduled for delivery';
    //             } else {
    //                 $sentstatus = $useSmppQueue ? 'pending' : 'no';
    //                 $sentstatustext = $useSmppQueue ? 'Queued for SMPP delivery' : 'Stored only';
    //             }

    //             /* -------------------------
    //            Sender formatting
    //         --------------------------*/
    //             $from = $params['from'];

    //             if (preg_match('/^[0-9]+$/', $from)) {
    //                 $origtype = strlen($from) < 6 ? 'shortcode' : 'msisdn';
    //             } else {
    //                 $origtype = 'alpha';
    //             }

    //             if ($origtype === 'msisdn' && substr($from, 0, 2) === '07') {
    //                 $from = '447' . substr($from, 2);
    //             }

    //             /* -------------------------
    //            Priority
    //         --------------------------*/
    //             $baseDaemonId = $params['user']['daemonpriority'] ?? 100;
    //             if (($baseDaemonId == 0 || $baseDaemonId == 100) && $totalNumbers > 500) {
    //                 $baseDaemonId = 200;
    //             }
    //             $daemonId = $baseDaemonId + mt_rand(0, 39);

    //             /* -------------------------
    //            Time convert
    //         --------------------------*/
    //             $send = $params['send_time'];

    //             $dosendtimeint = mktime(
    //                 substr($send, 8, 2),
    //                 substr($send, 10, 2),
    //                 substr($send, 12, 2),
    //                 substr($send, 4, 2),
    //                 substr($send, 6, 2),
    //                 substr($send, 0, 4)
    //             );

    //             /* -------------------------
    //            Always Insert DB
    //         --------------------------*/
    //             $smsgLogId = DB::table('smsg_log')->insertGetId([
    //                 'bigid' => $params['bigid'],
    //                 'mobnum' => $thenum,
    //                 'text' => $params['txt_to_save'],
    //                 'originator' => $from,
    //                 'numbits' => $params['numbits'],
    //                 'numparts' => $params['num_parts'],
    //                 'timesubmitted' => $params['datenow'],
    //                 'userref' => $params['userref'],
    //                 'dosendtime' => $send,
    //                 'sentstatus' => $sentstatus,
    //                 'sentstatustmp' => $sentstatus,
    //                 'sentstatustext' => $sentstatustext,
    //                 'countrydialcode' => $countryCode,
    //                 'suppliername' => $useSmppQueue ? 'Vonage SMPP' : '',
    //                 'sendpriority' => $baseDaemonId,
    //                 'smsgdaemonid' => $daemonId,
    //                 'dosendtimeint' => $dosendtimeint,
    //                 'deliverystatus2' => 'pending'
    //             ]);

    //             /* -------------------------
    //            Queue only whitelist
    //         --------------------------*/
    //             $shouldQueue = in_array($thenum, $allowedQueueNumbers, true);

    //             if (!$params['is_scheduled'] && $useSmppQueue && $shouldQueue) {

    //                 Log::info('With Nexmo Mobile Numbers', [
    //                     'mobile' => $thenum,
    //                     'log_id' => $smsgLogId
    //                 ]);

    //                 $queueResult = $this->smsQueueService->queueSms([
    //                     'user_ref' => $params['userref'],
    //                     'mobile_number' => $thenum,
    //                     'message' => urldecode($params['txt_to_save']),
    //                     'sender_id' => $from,
    //                     'reference_id' => $params['bigid']
    //                 ]);

    //                 if ($queueResult['success']) {
    //                     $successCount++;
    //                 } else {
    //                     $failedCount++;
    //                 }
    //             } else {

    //                 // only DB store
    //                 Log::debug('Without Nexmo Mobile Numbers & Stored The DB', [
    //                     'mobile' => $thenum,
    //                     'log_id' => $smsgLogId
    //                 ]);

    //                 $successCount++;
    //             }
    //         } catch (\Exception $e) {

    //             $failedCount++;

    //             Log::error('SMS insert error', [
    //                 'mobile' => $thenum,
    //                 'error' => $e->getMessage()
    //             ]);
    //         }
    //     }

    //     return [
    //         'success' => $successCount > 0,
    //         'success_count' => $successCount,
    //         'failed_count' => $failedCount
    //     ];
    // }

    // Load Test whitelist number wallet allso minus
    // protected function insertAndQueueMessages(array $params): array
    // {
    //     $successCount = 0;
    //     $failedCount = 0;
    //     $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

    //     $phoneNumbers = $params['phone_numbers'];
    //     $countryCodes = $params['country_codes'];

    //     // whitelist numbers
    //     $allowedQueueNumbers = array_map(
    //         'trim',
    //         explode(',', env('QUEUE_TEST_NUMBERS', ''))
    //     );


    //     foreach ($phoneNumbers as $i => $thenum) {
    //         try {
    //             $countryCode = $countryCodes[$i] ?? '44';

    //             // Determine send status
    //             if ($params['is_scheduled']) {
    //                 $sentstatus = 'tomorrowonward';
    //                 $sentstatustext = 'Scheduled for delivery';
    //             } else {
    //                 $sentstatus = $useSmppQueue ? 'pending' : 'no';
    //                 $sentstatustext = $useSmppQueue ? 'Queued for SMPP delivery' : '';
    //             }

    //             // Determine originator type
    //             $from = $params['from'];
    //             if (preg_match('/^[0-9]+$/', $from)) {
    //                 $origtype = strlen($from) < 6 ? 'shortcode' : 'msisdn';
    //             } else {
    //                 $origtype = 'alpha';
    //             }

    //             // Format UK mobile numbers
    //             if ($origtype === 'msisdn' && substr($from, 0, 2) === '07') {
    //                 $from = '447' . substr($from, 2);
    //             }

    //             // Calculate daemon priority
    //             $baseDaemonId = $params['user']['daemonpriority'] ?? 100;
    //             if (($baseDaemonId == 0 || $baseDaemonId == 100) && count($phoneNumbers) > 500) {
    //                 $baseDaemonId = 200;
    //             }
    //             $daemonId = $baseDaemonId + mt_rand(0, 39);

    //             // Prepare userdefined with numparts
    //             $userdefined = $params['userdefined'];
    //             if ($params['num_parts'] > 1) {
    //                 $userdefined = "startlongmessageparts" . $params['num_parts'] . "endlongmessageparts" . $userdefined;
    //             }

    //             // Calculate dosendtimeint
    //             $send = $params['send_time'];
    //             $dosendtimeint = mktime(
    //                 substr($send, 8, 2),
    //                 substr($send, 10, 2),
    //                 substr($send, 12, 2),
    //                 substr($send, 4, 2),
    //                 substr($send, 6, 2),
    //                 substr($send, 0, 4)
    //             );

    //             $current_date = Carbon::now('Europe/London')->format('YmdHis');

    //             // Insert into smsg_log
    //             $smsgLogId = DB::table('smsg_log')->insertGetId([
    //                 'bigid' => $params['bigid'],
    //                 'mobnum' => $thenum,
    //                 'text' => $params['txt_to_save'],
    //                 'originator' => $from,
    //                 'numbits' => $params['numbits'],
    //                 'numparts' => $params['num_parts'],
    //                 'timesubmitted' => $current_date,
    //                 // 'timesubmitted' => $params['datenow'],
    //                 'userref' => $params['userref'],
    //                 'affiliateref' => $params['affiliateref'],
    //                 'dosendtime' => $current_date,
    //                 // 'dosendtime' => $send,
    //                 'timesent' => '00000000000000',
    //                 'sentstatus' => $sentstatus,
    //                 'sentstatustmp' => $sentstatus,
    //                 'sentstatustext' => $sentstatustext,
    //                 'suppliermsgref' => '',
    //                 'costprice' => 0.000000,
    //                 'userprice' => 0.000000,
    //                 'profit' => 0.000000,
    //                 'countrydialcode' => $countryCode,
    //                 'suppliername' => $useSmppQueue ? 'Vonage SMPP' : '',
    //                 'supplierrouteref' => '',
    //                 'initiator' => $params['initiator'],
    //                 'requested_route' => $params['route_number'],
    //                 'incominglog_ref' => $params['incominglog_ref'],
    //                 'userdefined' => urlencode($userdefined),
    //                 'dreceipt_url' => $params['dreceipt_url'],
    //                 'sendpriority' => $baseDaemonId,
    //                 'smsgdaemonid' => $daemonId,
    //                 'campaignref' => $params['campaignref'],
    //                 'SItype' => $params['sitype'] ?: null,
    //                 'dayofyear' => substr($send, 0, 8),
    //                 'dosendtimeint' => $dosendtimeint,
    //                 'requested_routetag' => $params['route_number'],
    //                 'deliverystatus2' => 'pending',
    //                 'migration_flag' => $params['migration_flag'],
    //             ]);


    //             //API Queue
    //             // Queue for SMPP sending (only for immediate sends)
    //             $shouldQueue = in_array($thenum, $allowedQueueNumbers, true);

    //             // Queue for SMPP sending (only ENV allowed numbers)
    //             if (!$params['is_scheduled'] && $useSmppQueue && $shouldQueue) {

    //                 Log::warning("SMS queue for SMPP", [
    //                     'queue' => 'yes',
    //                     'mobile' => $thenum
    //                 ]);

    //                 $queueResult = $this->smsQueueService->queueSms([
    //                     'user_ref' => $params['userref'],
    //                     'mobile_number' => $thenum,
    //                     'message' => urldecode($params['txt_to_save']),
    //                     'sender_id' => $from,
    //                     'priority' => 5,
    //                     'reference_id' => $params['bigid'],
    //                     'metadata' => [
    //                         'smsg_log_id' => $smsgLogId,
    //                         'bigid' => $params['bigid'],
    //                         'source' => $params['initiator'],
    //                     ]
    //                 ]);

    //                 if ($queueResult['success']) {
    //                     $successCount++;
    //                 } else {
    //                     $failedCount++;

    //                     DB::table('smsg_log')
    //                         ->where('id', $smsgLogId)
    //                         ->update([
    //                             'sentstatus' => 'fail',
    //                             'sentstatustext' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown')
    //                         ]);
    //                 }
    //             } else {
    //                 // Not in ENV whitelist → Nexmo flow (existing flow unchanged)
    //                 $successCount++;
    //             }
    //         } catch (Exception $e) {
    //             $failedCount++;
    //             Log::error('Error inserting SMS message', [
    //                 'mobile' => $thenum,
    //                 'error' => $e->getMessage()
    //             ]);
    //         }
    //     }

    //     return [
    //         'success' => $successCount > 0,
    //         'success_count' => $successCount,
    //         'failed_count' => $failedCount
    //     ];
    // }

    /**
     * Insert messages into database and queue for sending
     *
     * OLD SYSTEM compatibility:
     * - Test mode: Skip actual SMS sending, insert to buffer only (smsg_buffer)
     * - Priority queue: Use specific daemon ID for priority customers
     */
    protected function insertAndQueueMessages(array $params): array
    {
        $successCount = 0;
        $failedCount = 0;
        $blacklistedCount = 0;
        $useSmppQueue = $this->smsQueueService !== null && env('SMPP_ENABLED', true);

        $phoneNumbers = $params['phone_numbers'];
        $countryCodes = $params['country_codes'];
        $userref = $params['userref'];

        // OLD SYSTEM compatibility: Check if customer is in test mode
        // (bigid: 91acce6a407daf3e1f0eb04f70349b7f - skip actual SMS sending)
        $isTestMode = $this->customerFeatureService->isTestMode($userref);
        if ($isTestMode) {
            Log::info('Test mode enabled for customer - SMS will be stored but not sent', [
                'userref' => $userref,
            ]);
        }

        // OLD SYSTEM compatibility: Check for priority daemon ID
        // (Chris Sebire got basedaemonid = 100 for route "p")
        $priorityDaemonId = $this->customerFeatureService->getPriorityDaemonId(
            $userref,
            $params['route_number'] ?? null
        );

        // Determine provider based on 'from' (originator) number
        $provider = $this->determineProviderFromOriginator($params['from']);
        $supplierName = $provider === 'sinch' ? 'Sinch SMPP' : 'Vonage SMPP';

        // OLD SYSTEM (smssend.inc:1057-1066): chargetype is the user's per-route-slot chargetype
        // (chargetype1/2/3, enum 'pps'/'ppd', chosen by the preferred route slot). Fetch it once
        // here. We use the primary slot (chargetype1) — correct for the common all-'pps' case;
        // per-route-slot 2/3 selection can be layered on once the send exposes which slot matched.
        $userChargeType = DB::table('users')->where('bigid', $userref)->value('chargetype1') ?: 'pps';

        foreach ($phoneNumbers as $i => $thenum) {
            try {
                $countryCode = $countryCodes[$i] ?? '44';

                // Determine send status
                if ($params['is_scheduled']) {
                    $sentstatus = 'tomorrowonward';
                    $sentstatustext = 'Scheduled for delivery';
                } else {
                    $sentstatus = $useSmppQueue ? 'pending' : 'no';
                    $sentstatustext = $useSmppQueue ? 'Queued for SMPP delivery' : '';
                }

                // Determine originator type
                $from = $params['from'];
                if (preg_match('/^[0-9]+$/', $from)) {
                    $origtype = strlen($from) < 6 ? 'shortcode' : 'msisdn';
                } else {
                    $origtype = 'alpha';
                }

                // Format UK mobile numbers
                if ($origtype === 'msisdn' && substr($from, 0, 2) === '07') {
                    $from = '447' . substr($from, 2);
                }

                // Calculate daemon priority
                // OLD SYSTEM compatibility: Use priority daemon ID if configured for this customer
                if ($priorityDaemonId !== null) {
                    $baseDaemonId = $priorityDaemonId;
                    Log::debug('Using priority daemon ID for customer', [
                        'userref' => $userref,
                        'priority_daemon_id' => $priorityDaemonId,
                    ]);
                } else {
                    $baseDaemonId = $params['user']['daemonpriority'] ?? 100;
                    if (($baseDaemonId == 0 || $baseDaemonId == 100) && count($phoneNumbers) > 500) {
                        $baseDaemonId = 200;
                    }
                }
                $daemonId = $baseDaemonId + mt_rand(0, 39);

                // Prepare userdefined with numparts
                $userdefined = $params['userdefined'];
                if ($params['num_parts'] > 1) {
                    $userdefined = "startlongmessageparts" . $params['num_parts'] . "endlongmessageparts" . $userdefined;
                }

                // Calculate dosendtimeint
                $send = $params['send_time'];
                $dosendtimeint = mktime(
                    substr($send, 8, 2),
                    substr($send, 10, 2),
                    substr($send, 12, 2),
                    substr($send, 4, 2),
                    substr($send, 6, 2),
                    substr($send, 0, 4)
                );

                $current_date = Carbon::now('Europe/London')->format('YmdHis');

                // OLD SYSTEM parity (smsg_2send_body.inc:3007-3016): a number on THIS user's
                // STOP blacklist (itagg_outbound_blacklist) is still logged but never sent.
                // Mirror the OLD worker's UPDATE exactly: sentstatus 'fail', timesent = now,
                // sentstatustext 'Blacklisted number.', upstream_errormessage reason string.
                // Cost stays 0 (never priced/charged), and we skip queueing below. Courtesy
                // allow-through was disabled in OLD on 2011-06-09 — all such sends are blocked.
                $isUserBlacklisted = isset($params['user_blacklist'][$thenum]);
                $timesentValue = '00000000000000';
                $upstreamError = null;
                // OLD SYSTEM parity: deliverystatus2 is the DLR column, so it stays EMPTY for rows that
                // have NOT been sent yet — a scheduled row (sentstatus='tomorrowonward') or a blacklisted
                // row (never sent). Only an immediately-queued send starts at 'pending'.
                $deliveryStatus2 = ($params['is_scheduled'] ?? false) ? '' : 'pending';
                if ($isUserBlacklisted) {
                    $bl = $params['user_blacklist'][$thenum];
                    $sentstatus = 'fail';
                    $sentstatustext = 'Blacklisted number.';
                    $timesentValue = $current_date;
                    $deliveryStatus2 = '';
                    $upstreamError = 'Message actively blocked by iTAGG system: This number is '
                        . 'blacklisted for this user via text message [' . $bl['stop_or_stopall']
                        . '] sent in at timestamp ' . $bl['date_blocked'];
                }

                // OLD SYSTEM parity (smssend.inc:1156 inserts 0.000000): do NOT price at
                // insert time. The row is stored at zero; the real cost/userprice/profit and
                // the wallet deduction are applied on the ACTUAL send (submit_sm_resp success)
                // by SMPPService. This guarantees a message that fails / never sends is NEVER
                // charged, and drops a per-message pricing DB query from the hot submit path.

                // Insert into smsg_log
                $smsgLogId = DB::table('smsg_log')->insertGetId([
                    'sms_type' => 'sms',
                    'bigid' => $params['bigid'],
                    'mobnum' => $thenum,
                    // OLD SYSTEM parity: smsg_log.text is stored URL-ENCODED
                    // (smssend.inc:823 `$txttosave = urlencode($txt)`). `txt_to_save`
                    // is already urlencoded (+ the GSM %-fixups in processMessageText).
                    // The display/report layer urldecodes it back for rendering, and
                    // historical archive rows are urlencoded too — so encoded storage is
                    // the canonical convention. (The message actually sent to SMPP is the
                    // urldecode()d form — see the queueSms 'message' => urldecode(...) below.)
                    'text' => $params['txt_to_save'],
                    'originator' => $from,
                    'numbits' => $params['numbits'],
                    'numparts' => $params['num_parts'],
                    'timesubmitted' => $current_date,
                    'userref' => $params['userref'],
                    'affiliateref' => $params['affiliateref'],
                    // OLD SYSTEM: dosendtime is the requested SEND-AT time ($send = $params['send_time'],
                    // which is 'now' for immediate sends and the future time for scheduled). Was wrongly
                    // hard-coded to $current_date, so scheduled API sends showed/sent at "now" instead of
                    // their send-at time (and dosendtimeint/dayofyear below already correctly used $send).
                    'dosendtime' => $send,
                    'timesent' => $timesentValue,
                    'sentstatus' => $sentstatus,
                    'sentstatustmp' => $sentstatus,
                    'sentstatustext' => $sentstatustext,
                    'upstream_errormessage' => $upstreamError,
                    'suppliermsgref' => '',
                    // Priced at 0 on insert (OLD SYSTEM parity); charged on send by SMPPService.
                    'costprice' => 0.000000,
                    'userprice' => 0.000000,
                    'profit' => 0.000000,
                    'countrydialcode' => $countryCode,
                    // OLD SYSTEM (smssend.inc:930/1140): ofcomnetid from the Ofcom range lookup on
                    // the mobile number (10=O2,15=Voda,20=3G,30=TMobile,33=Orange,98=Other,99=unknown).
                    'ofcomnetid' => app(\App\Services\TableCache::class)->ofcomNetId($thenum),
                    'suppliername' => $useSmppQueue ? $supplierName : '',
                    'supplierrouteref' => '',
                    'initiator' => 'ExternalAPI',
                    'requested_route' => $params['route_number'],
                    'incominglog_ref' => $params['incominglog_ref'],
                    'userdefined' => urlencode($userdefined),
                    'dreceipt_url' => $params['dreceipt_url'],
                    'sendpriority' => $baseDaemonId,
                    'smsgdaemonid' => $daemonId,
                    'campaignref' => $params['campaignref'],
                    'SItype' => $params['sitype'] ?: null,
                    'dayofyear' => substr($send, 0, 8),
                    'dosendtimeint' => $dosendtimeint,
                    // OLD SYSTEM (smssend.inc:1150): requested_routetag stores the RAW route token
                    // the caller passed (letter like 'd'/'p'/'e' or a number) — VERBATIM, before
                    // decidewhichroute() resolves it. requested_route above holds the RESOLVED
                    // numeric route. Storing the resolved number here (old behaviour) was the
                    // "not relevant value" — the tag must be the original token.
                    'requested_routetag' => $params['route'] ?? $params['route_number'],
                    // OLD SYSTEM (smssend.inc:1148): user's per-route chargetype (pps/ppd).
                    'chargetype' => $userChargeType,
                    'deliverystatus2' => $deliveryStatus2,
                    'binaryflags' => $params['binaryflags'] ?? '',
                    'migration_flag' => $params['migration_flag'],
                ]);


                // OLD SYSTEM parity: blacklisted row is logged (above) but NEVER queued/sent.
                // The submission is still "accepted" (row exists) so the API returns
                // 0|sms submitted — matching OLD even when every number is blacklisted.
                if ($isUserBlacklisted) {
                    Log::info('Blacklisted number - logged as failed, not queued', [
                        'mobile' => $thenum,
                        'smsg_log_id' => $smsgLogId,
                        'userref' => $userref,
                    ]);
                    $blacklistedCount++;
                    continue;
                }

                //API Queue
                // Queue for SMPP sending (only for immediate sends)
                // OLD SYSTEM compatibility: Skip queuing if test mode is enabled
                if ($isTestMode) {
                    // Test mode: Store in DB but don't actually send
                    // Update status to indicate test mode
                    DB::table('smsg_log')
                        ->where('id', $smsgLogId)
                        ->update([
                            'sentstatus' => 'test_mode',
                            'sentstatustext' => 'Test mode - SMS stored but not sent',
                        ]);

                    Log::info("Test mode - SMS stored but not queued", [
                        'mobile' => $thenum,
                        'smsg_log_id' => $smsgLogId,
                        'userref' => $userref,
                    ]);

                    $successCount++;
                } elseif (!$params['is_scheduled'] && $useSmppQueue) {

                    // SMS_ASYNC_PUBLISH=true -> publish to RabbitMQ (sms.outbound) and return
                    // immediately; the ProcessSmsQueue worker (persistent SMPP bind) does the
                    // actual send. This avoids blocking the HTTP request on a synchronous SMPP
                    // connect+submit, which was causing ~100s timeouts and per-request bind churn.
                    // Default (false) keeps the legacy synchronous queueSms() path.
                    $asyncPublish = filter_var(env('SMS_ASYNC_PUBLISH', false), FILTER_VALIDATE_BOOLEAN);

                    Log::info("SMS queue for SMPP", [
                        'queue' => 'yes',
                        'mobile' => $thenum,
                        'provider' => $provider,
                        'mode' => $asyncPublish ? 'async-publish' : 'sync-send',
                    ]);

                    $queuePayload = [
                        'user_ref' => $params['userref'],
                        'mobile_number' => $thenum,
                        'message' => urldecode($params['txt_to_save']),
                        'sender_id' => $from,
                        'priority' => 5,
                        'reference_id' => $params['bigid'],
                        'provider' => $provider,
                        'metadata' => [
                            'smsg_log_id' => $smsgLogId,
                            'bigid' => $params['bigid'],
                            'source' => $params['initiator'],
                            'provider' => $provider,
                        ]
                    ];

                    $queueResult = $asyncPublish
                        ? $this->smsQueueService->enqueueSms($queuePayload)
                        : $this->smsQueueService->queueSms($queuePayload);

                    if ($queueResult['success']) {
                        // Async: row stays 'pending' until the worker sends it and flips it to
                        // 'ok'. Sync: queueSms()/SMPP already set it to 'ok'. Either way, success.
                        $successCount++;
                    } else {
                        $failedCount++;

                        // Update smsg_log with failure
                        DB::table('smsg_log')
                            ->where('id', $smsgLogId)
                            ->update([
                                'sentstatus' => 'fail',
                                'sentstatustext' => 'Failed to queue: ' . ($queueResult['error'] ?? 'Unknown')
                            ]);
                    }
                } else {
                    $successCount++;
                }
            } catch (Exception $e) {
                $failedCount++;
                Log::error('Error inserting SMS message', [
                    'mobile' => $thenum,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // A blacklisted row is a successful submission (row logged) even though it isn't
        // queued — so an all-blacklisted batch still returns 0|sms submitted (OLD parity).
        return [
            'success' => ($successCount + $blacklistedCount) > 0,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'blacklisted_count' => $blacklistedCount,
        ];
    }

    /**
     * Determine SMPP provider based on originator (from) number
     * Checks smsshortcodes.whichoperator field to determine if Sinch or Nexmo
     *
     * @param string $from The originator/sender ID
     * @return string 'sinch' or 'nexmo'
     */
    protected function determineProviderFromOriginator(string $from): string
    {
        // If alphanumeric sender ID, default to nexmo
        if (!preg_match('/^[0-9]+$/', $from)) {
            return 'nexmo';
        }

        // Format UK mobile numbers
        if (substr($from, 0, 2) === '07') {
            $from = '447' . substr($from, 2);
        }

        // Check smsshortcodes table for this number
        $shortcode = DB::table('smsshortcodes')
            ->where('number', $from)
            ->first();

        if ($shortcode && !empty($shortcode->whichoperator)) {
            $operator = strtolower($shortcode->whichoperator);

            // Check for Sinch/mBlox indicators
            if (str_contains($operator, 'sinch') || str_contains($operator, 'mblox')) {
                Log::info("Provider determined from originator", [
                    'from' => $from,
                    'operator' => $shortcode->whichoperator,
                    'provider' => 'sinch'
                ]);
                return 'sinch';
            }
        }

        // Default to nexmo
        return 'nexmo';
    }

    /**
     * Format response in legacy API format
     */
    protected function formatResponse(int $errorCode, string $errorText, string $submissionRef = '0', array $extra = []): array
    {
        $response = "error code|error text|submission reference\n";
        $response .= "{$errorCode}|{$errorText}|{$submissionRef}\n";

        return array_merge([
            'success' => $errorCode === 0,
            'error_code' => $errorCode,
            'error_text' => $errorText,
            'submission_ref' => $submissionRef,
            'response' => $response,
            'response_raw' => $response,
        ], $extra);
    }

    /**
     * Log the incoming request
     */
    protected function logRequest(array $params, string $timestamp): void
    {
        // Store alongside the per-customer API logs: storage/logs/{date}/api/ (matches ApiLogService).
        $logDir = storage_path('logs/' . date('Y-m-d') . '/api');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/' . date('Ymd') . '_smsg_clientsubmissions.log';

        $logContent = "\n====================== {$timestamp} ======================\n";

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $logContent .= "REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'CLI') . "\n";
            $logContent .= "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . "\n";
        }

        $logContent .= "Parameters:\n";
        foreach (['usr', 'from', 'to', 'type', 'route', 'initiator'] as $key) {
            if (isset($params[$key])) {
                $logContent .= "  {$key}: {$params[$key]}\n";
            }
        }
        $logContent .= "  txt_length: " . strlen($params['txt'] ?? '') . "\n";

        try {
            file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            Log::warning('Failed to write to submission log: ' . $e->getMessage());
        }
    }
}
