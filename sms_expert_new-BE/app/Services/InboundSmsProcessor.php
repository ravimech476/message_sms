<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\User;
use App\Models\SmsShortcode;
use Exception;

/**
 * Inbound SMS Processor Service
 * 
 * This service handles the actual processing of inbound SMS messages.
 * It is called by the queue consumer to process messages one by one.
 */
class InboundSmsProcessor
{
    const GRACE_PERIOD = 5; // Days
    const NO_BIGID = 0;
    const NO_OPERATOR_MESSAGE_ID = -1;

    // Module bit definitions (from legacy code)
    const MODULE_SMS_RESPONDER = 1;
    const MODULE_FORWARDER = 2;          // Email Forwarder / Developer package
    const MODULE_SMS_FORWARDER = 4;
    const MODULE_BUSINESS_CARD = 8;
    const MODULE_SUBSCRIPTION = 16;
    const MODULE_WAP_PUSH_RESPONDER = 32;
    const MODULE_LOCATION_AND_MAPPING = 64;
    const MODULE_WAP_PAGE_RESPONSE = 128;
    const MODULE_VOTING = 256;
    const MODULE_MAP_RESPONSE = 512;
    const MODULE_APPLICATION_DOWNLOAD = 1024;
    const MODULE_EMAIL_FORWARDER = 2048;
    const MODULE_LOCATION_PLUS = 4096;
    const MODULE_MMS_EMAIL_FORWARDER = 8192;
    const MODULE_MMS_FORWARDER = 16384;
    const MODULE_MMS_FORWARDER_PLUS = 32768;

    protected $debug = true;
    protected $debugLog = [];
    protected $mostrecentuser = '';
    protected $requestId = '';

    /**
     * Process an inbound SMS message
     * 
     * @param array $inboundData The parsed inbound message data
     * @return bool True on success, false on failure
     */
    public function process(array $inboundData): bool
    {
        $this->requestId = $inboundData['request_id'] ?? uniqid('proc_', true);
        $this->debugLog = [];

        $this->log('=== Inbound SMS Processor Started ===');
        $this->log('Request ID: ' . $this->requestId);

        try {
            // Extract data from queue message
            $data = $inboundData['data'] ?? $inboundData;
            
            $source = $data['source'];
            $dest = $data['dest'];
            $message = $data['message'];
            $network = $data['network'] ?? '99';
            $operatorMessageId = $data['operator_message_id'] ?? self::NO_OPERATOR_MESSAGE_ID;
            $operatorId = $data['operator_id'] ?? '';

            $this->log("Processing: $source -> $dest | Message: $message");

            // HTML decode message
            $message = $this->decodeMessage($message);

            // Update/Create mobile detail record
            $mobileDetailId = $this->updateMobileDetail($source, $network, $operatorId);

            // Parse message into keyword, subkeyword, and rest
            $messageParts = $this->parseMessage($message);
            $keyword = $messageParts['keyword'];
            $subkeyword = $messageParts['subkeyword'];
            $rest = $messageParts['rest'];

            $this->log("Parsed - Keyword: $keyword, Subkeyword: $subkeyword, Rest: $rest");

            // Check if this is a STOP command
            $isStopCommand = $this->isStopCommand($message);

            // Check for alias rules and transform message if needed (only if NOT a STOP command)
            if (!$isStopCommand) {
                $aliasResult = $this->processAliasRules($keyword, $subkeyword, $dest, $message);
                if ($aliasResult['transformed']) {
                    $message = $aliasResult['new_message'];
                    $messageParts = $this->parseMessage($message);
                    $keyword = $messageParts['keyword'];
                    $subkeyword = $messageParts['subkeyword'];
                    $rest = $messageParts['rest'];
                    $this->log("Alias applied - New message: $message");
                }
            }

            // Find matching iTAGG instance
            $itaggInstance = $this->findItaggInstance($keyword, $dest);

            if (!$itaggInstance) {
                // No match found - check for STOP commands on shared shortcodes
                if ($isStopCommand) {
                    $this->handleUnmatchedStopCommand($source, $dest, $message, $network, $operatorMessageId, $operatorId, $mobileDetailId);
                } else {
                    $this->logIncoming($keyword, $subkeyword, $source, $dest, $message, $network, 0, self::NO_BIGID, $operatorMessageId, $operatorId);
                }
                $this->log('No iTAGG instance matched');
                return true; // Still successful processing, just no match
            }

            // Extract iTAGG instance details
            $itaggId = $itaggInstance->id;
            $usersBigid = $itaggInstance->users_bigid;
            $this->log("Matched iTAGG ID: $itaggId, User BigID: $usersBigid");

            // Log the incoming message
            $incomingLogId = $this->logIncoming(
                $keyword,
                $subkeyword,
                $source,
                $dest,
                $message,
                $network,
                1, // matched
                $usersBigid,
                $operatorMessageId,
                $operatorId
            );

            $this->log("Incoming log ID: $incomingLogId");

            // Handle STOP/STOP ALL commands OR remove from blacklist
            if ($isStopCommand) {
                // User sent STOP - add/update blacklist
                $this->handleStopCommand($source, $dest, $message, $usersBigid, $mobileDetailId, $incomingLogId);
            } else {
                // User sent any other message - they're re-engaging, remove from blacklist
                $this->removeFromBlacklist($source, $usersBigid, $dest);
                
                // Manage user groups and address book (only for non-STOP messages)
                $this->manageUserGroupsAndAddressBook($keyword, $subkeyword, $source, $usersBigid, $mobileDetailId);
                
                // Get module configuration (checks for subkeyword override)
                $moduleConfig = $this->getModuleConfiguration($itaggInstance, $itaggId, $subkeyword);
                
                // Handle URL forwarding, email forwarding, and other modules using bitfield logic
                $this->processModulesWithBitfield($moduleConfig, $itaggInstance, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $usersBigid, $network);
            }

            $this->log('=== Inbound SMS Processor Completed ===');
            return true;

        } catch (Exception $e) {
            $this->log('ERROR: ' . $e->getMessage());
            $this->log('Stack trace: ' . $e->getTraceAsString());
            
            Log::error('Inbound SMS Processor Error', [
                'request_id' => $this->requestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Get module configuration - checks for subkeyword override
     */
    protected function getModuleConfiguration($itaggInstance, $itaggId, $subkeyword)
    {
        // Default configuration from main itagg_instance
        $config = [
            'response_sender_id' => $itaggInstance->response_sender_id ?? '',
            'response_content' => $itaggInstance->response_content ?? '',
            'response_smsshortcodes_id' => $itaggInstance->response_smsshortcodes_id ?? 0,
            'forwarding_email' => $itaggInstance->forwarding_email ?? '',
            'forwarding_url' => $itaggInstance->forwarding_url ?? '',
            'modules_enabled' => $itaggInstance->modules_enabled ?? 0,
            'is_subkeyword' => false,
            'subkeyword' => ''
        ];

        // If no subkeyword provided, return main config
        if (empty($subkeyword)) {
            $this->log("Using main keyword configuration (no subkeyword)");
            return $config;
        }

        // Check for subkeyword configuration in itagg_subkeyword table
        $subkeywordConfig = DB::table('itagg_subkeyword')
            ->where('itagg_instance_id', $itaggId)
            ->where('keyword', $subkeyword)
            ->first();

        if ($subkeywordConfig) {
            $this->log("Subkeyword matched: '$subkeyword' - using subkeyword configuration");
            
            // Override with subkeyword configuration
            $config['response_sender_id'] = $subkeywordConfig->response_sender_id ?? $config['response_sender_id'];
            $config['response_content'] = $subkeywordConfig->response_content ?? $config['response_content'];
            $config['response_smsshortcodes_id'] = $subkeywordConfig->response_smsshortcodes_id ?? $config['response_smsshortcodes_id'];
            $config['forwarding_email'] = $subkeywordConfig->forwarding_email ?? $config['forwarding_email'];
            $config['forwarding_url'] = $subkeywordConfig->forwarding_url ?? $config['forwarding_url'];
            $config['modules_enabled'] = $subkeywordConfig->modules_enabled ?? $config['modules_enabled'];
            $config['is_subkeyword'] = true;
            $config['subkeyword'] = $subkeyword;
        } else {
            $this->log("No subkeyword configuration found for '$subkeyword' - using main keyword configuration");
        }

        return $config;
    }

    /**
     * Process modules using bitfield logic
     */
    protected function processModulesWithBitfield($moduleConfig, $itaggInstance, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $usersBigid, $network)
    {
        // Create forwarding log entry
        $forwardingLogId = DB::table('itagg_forwardinglog')->insertGetId([
            'itagg_incominglog_id' => $incomingLogId,
            'forwarded_email' => '',
            'forwarded_url' => ''
        ]);

        $modulesEnabled = (int) $moduleConfig['modules_enabled'];
        $forwardingEmail = $moduleConfig['forwarding_email'];
        $forwardingUrl = $moduleConfig['forwarding_url'];
        $itaggId = $itaggInstance->id;

        $this->log("Processing modules - modules_enabled bitfield: $modulesEnabled");

        // If modules_enabled is 0, fall back to simple forwarding
        if ($modulesEnabled == 0) {
            $this->log("No modules_enabled bitfield set - using direct forwarding fields");
            
            if (!empty($forwardingUrl)) {
                $this->forwardToUrl($forwardingUrl, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $forwardingLogId, $itaggId, $usersBigid);
            }
            
            if (!empty($forwardingEmail)) {
                $this->forwardToEmail($forwardingEmail, $source, $dest, $message, $keyword, $subkeyword, $rest, $network, $itaggId, $usersBigid);
            }
            
            return;
        }

        // Process enabled modules using bitfield logic
        $bitFlag = 1;
        while ($modulesEnabled > 0) {
            if (($modulesEnabled & $bitFlag) == $bitFlag) {
                $moduleStartTime = time();
                
                $moduleRecord = DB::table('itagg_module')
                    ->where('id', $bitFlag)
                    ->first();

                if ($moduleRecord) {
                    $moduleName = $moduleRecord->name;
                    $this->log("Processing module: $moduleName (bit: $bitFlag)");

                    $this->executeModule(
                        $bitFlag,
                        $moduleName,
                        $moduleConfig,
                        $itaggInstance,
                        $source,
                        $dest,
                        $message,
                        $keyword,
                        $subkeyword,
                        $rest,
                        $incomingLogId,
                        $forwardingLogId,
                        $usersBigid,
                        $network
                    );

                    $timeTaken = time() - $moduleStartTime;
                    if ($timeTaken >= 20) {
                        $this->logSlowProcess($bitFlag, $timeTaken, $keyword, $subkeyword, $dest, $incomingLogId, $moduleName, $source, $message, $network);
                    }
                    
                    $this->log("Module $moduleName completed in {$timeTaken} seconds");
                }

                $modulesEnabled -= $bitFlag;
            }
            $bitFlag += $bitFlag;
        }
    }

    /**
     * Execute specific module based on bit flag
     */
    protected function executeModule($bitFlag, $moduleName, $moduleConfig, $itaggInstance, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $forwardingLogId, $usersBigid, $network)
    {
        $itaggId = $itaggInstance->id;
        $forwardingEmail = $moduleConfig['forwarding_email'];
        $forwardingUrl = $moduleConfig['forwarding_url'];
        $responseSenderId = $moduleConfig['response_sender_id'];
        $responseContent = $moduleConfig['response_content'];
        $responseSmsshortcodesId = $moduleConfig['response_smsshortcodes_id'];

        switch ($bitFlag) {
            case self::MODULE_SMS_RESPONDER:
                $this->log("MODULE 1 - SMS Auto-Responder");
                $this->executeSmsResponder($responseSenderId, $responseContent, $responseSmsshortcodesId, $source, $itaggId, $usersBigid);
                break;

            case self::MODULE_FORWARDER:
                $this->log("MODULE 2 - Forwarder (Email/URL)");
                if (!empty($forwardingUrl)) {
                    $this->forwardToUrl($forwardingUrl, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $forwardingLogId, $itaggId, $usersBigid, $network);
                }
                if (!empty($forwardingEmail)) {
                    $this->forwardToEmail($forwardingEmail, $source, $dest, $message, $keyword, $subkeyword, $rest, $network, $itaggId, $usersBigid);
                }
                DB::table('itagg_forwardinglog')
                    ->where('id', $forwardingLogId)
                    ->update([
                        'forwarded_email' => $forwardingEmail,
                        'forwarded_url' => $forwardingUrl,
                        'forwarded_email_timestamp' => now('Europe/London')->format('YmdHis'),
                    ]);
                break;

            case self::MODULE_SMS_FORWARDER:
                $this->log("MODULE 4 - SMS Forwarder");
                $this->executeSmsForwarder($keyword, $subkeyword, $dest, $source, $message, $itaggId, $usersBigid);
                break;

            case self::MODULE_BUSINESS_CARD:
                $this->log("MODULE 8 - Business Card");
                $this->executeBusinessCard($itaggId, $subkeyword, $source, $usersBigid, $incomingLogId);
                break;

            case self::MODULE_SUBSCRIPTION:
                $this->log("MODULE 16 - Subscription");
                $this->executeSubscription($keyword, $subkeyword, $dest, $source, $message, $rest, $itaggId, $usersBigid);
                break;

            case self::MODULE_WAP_PUSH_RESPONDER:
                $this->log("MODULE 32 - WAP Push Responder");
                $this->executeWapPushResponder($keyword, $subkeyword, $dest, $source, $itaggId, $usersBigid);
                break;

            case self::MODULE_EMAIL_FORWARDER:
                $this->log("MODULE 2048 - Email Forwarder");
                // Old EmailForwarderBase delegates to ForwarderBase, which forwards to URL too.
                if (!empty($forwardingUrl)) {
                    $this->forwardToUrl($forwardingUrl, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $forwardingLogId, $itaggId, $usersBigid, $network);
                }
                if (!empty($forwardingEmail)) {
                    $this->forwardToEmail($forwardingEmail, $source, $dest, $message, $keyword, $subkeyword, $rest, $network, $itaggId, $usersBigid);
                }
                break;

            case self::MODULE_VOTING:
                $this->log("MODULE 256 - Voting");
                $this->executeVoting($keyword, $subkeyword, $dest, $source, $rest, $itaggId, $usersBigid, $incomingLogId, $network);
                break;

            default:
                $this->log("Module $bitFlag ($moduleName) not implemented");
                break;
        }
    }

    /**
     * Execute SMS Auto-Responder module
     */
    protected function executeSmsResponder($senderId, $content, $shortcodeId, $source, $itaggId, $usersBigid)
    {
        if (empty($content)) {
            $this->log("SMS Responder: No response content configured");
            return;
        }

        $responseText = urldecode($content);
        
        $shortcode = DB::table('smsshortcodes')
            ->where('id', $shortcodeId)
            ->first();

        $originator = $senderId ?: ($shortcode ? $shortcode->number : 'iTagg');

        $this->log("SMS Responder: Sending response to $source from $originator");

        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2),
            (int) substr($datenow, 10, 2),
            (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2),
            (int) substr($datenow, 6, 2),
            (int) substr($datenow, 0, 4)
        );

        // Daemon priority for auto-responses
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        DB::table('smsg_log')->insert([
            'sms_type' => 'sms',
            'initiator' => 'iTagg',
            'bigid' => $bigid,
            'mobnum' => $source,
            'numparts' => $this->calculateSmsParts($responseText),
            'text' => $content,
            'originator' => $originator,
            'numbits' => 7,
            'timesubmitted' => $datenow,
            'userref' => $usersBigid,
            'affiliateref' => '0',
            'dosendtime' => $datenow,
            'dosendtimeint' => $dosendtimeint,
            'dayofyear' => substr($datenow, 0, 8),
            'timesent' => '00000000000000',
            'sentstatus' => 'pending',
            'sentstatustmp' => 'pending',
            'sentstatustext' => 'iTAGG Auto-Response',
            'suppliermsgref' => '',
            'smsgdaemonid' => $daemonId,
            'sendpriority' => $baseDaemonId,
            'costprice' => 0.000000,
            'userprice' => 0.000000,
            'aggregator_dlrcode' => 0,
            'aggregator_dlrmsg' => 'Queued',
            'campaignref' => '',
            'binaryflags' => '',
            'profit' => 0.000000,
            'countrydialcode' => substr($source, 0, 2) == '44' ? '44' : '',
            'suppliername' => '',
            'supplierrouteref' => '',
            'requested_route' => 0,
            'requested_routetag' => '',
            'deliverystatus2' => 'pending',
            'migration_flag' => 'new',
        ]);

        $this->log("SMS Responder: Response queued for delivery");
    }

    /**
     * Execute Voting module (bit 256) — records an inbound vote and sends the
     * success/failure response, mirroring the old VotingBase::incomingHandler.
     */
    protected function executeVoting($keyword, $subkeyword, $shortcodeNumber, $source, $rest, $itaggId, $usersBigid, $incomingLogId, $network)
    {
        // 1. Load the campaign (subkeyword NULL when empty, else exact match).
        $q = DB::table('itagg_module_voting')->where('itagg_id', $itaggId);
        if ($subkeyword === null || $subkeyword === '') {
            $q->whereNull('subkeyword');
        } else {
            $q->where('subkeyword', $subkeyword);
        }
        $voting = $q->first();
        if (!$voting) {
            $this->log("Voting: no campaign for itagg_id=$itaggId subkeyword=" . ($subkeyword ?: '(none)'));
            return;
        }
        if ((int) $voting->suspended === 1) {
            $this->log("Voting: campaign {$voting->id} is suspended");
            return;
        }

        $votingId = $voting->id;
        $word = strtolower(trim((string) $rest));

        // 2. Empty word + eligibleMobiles set => send a stats-by-mobile tally.
        if ($word === '') {
            if (!empty($voting->eligibleMobiles)) {
                $eligible = trim($voting->eligibleMobiles);
                $allowed = ($eligible === 'all')
                    || in_array($source, array_map('trim', explode(',', $eligible)), true);
                if ($allowed) {
                    $this->sendVotingResponse($this->compileVotingSbm($votingId), $voting->sbmRoute, $source, $usersBigid);
                }
            }
            return;
        }

        // 3. Recognised word?
        $wordRow = DB::table('itagg_module_voting_word')
            ->where('voting_id', $votingId)->where('word', $word)->first();
        if (!$wordRow) {
            $this->log("Voting: word '$word' not recognised for campaign {$votingId}");
            return;
        }

        // 4. Success/failure responses.
        $responses = DB::table('itagg_module_voting_response')
            ->where('voting_id', $votingId)->get()->keyBy('result');
        $success = $responses->get('success');
        $failure = $responses->get('failure');

        $successful = 1;
        $resp = $success;

        // 5. Date-range gate.
        $today = date('Y-m-d');
        if (!empty($voting->start) && !empty($voting->finish)
            && ($today < $voting->start || $today > $voting->finish)) {
            $successful = 0;
            $resp = $failure;
        }

        // 6. frequency='once' dedupe.
        if ($successful === 1 && $voting->frequency === 'once') {
            $already = DB::table('itagg_module_voting_record')
                ->where('voting_id', $votingId)->where('mobile', $source)
                ->where('successful', 1)->exists();
            if ($already) {
                $successful = 0;
                $resp = $failure;
            }
        }

        // 7. Record the vote (insertOrIgnore: PK is voting_id+mobile+received+voted_for).
        DB::table('itagg_module_voting_record')->insertOrIgnore([
            'voting_id'  => $votingId,
            'mobile'     => $source,
            'voted_for'  => $word,
            'successful' => $successful,
        ]);

        // 8. Send the response, substituting %v with the voted word.
        if ($resp && !empty($resp->response_text)) {
            $text = str_replace('%v', $word, urldecode($resp->response_text));
            $this->sendVotingResponse($text, $resp->response_route, $source, $usersBigid);
        }

        $this->log("Voting: recorded word='$word' successful=$successful for campaign {$votingId}");
    }

    /**
     * Top-3 vote tally as "word : N vote(s)\r\n" (old VotingBase::compileSBM).
     */
    protected function compileVotingSbm($votingId)
    {
        $rows = DB::table('itagg_module_voting_record')
            ->select('voted_for', DB::raw('COUNT(*) as cnt'))
            ->where('voting_id', $votingId)->where('successful', 1)
            ->groupBy('voted_for')->orderByDesc('cnt')->limit(3)->get();

        $out = '';
        foreach ($rows as $r) {
            $out .= $r->voted_for . ' : ' . $r->cnt . ' vote' . ($r->cnt == 1 ? '' : 's') . "\r\n";
        }
        return $out !== '' ? $out : 'No votes recorded yet.';
    }

    /**
     * Queue a voting confirmation/tally SMS back to the voter.
     * (Route parity with the old premium/standard split is a known cross-cutting gap;
     *  the sending daemon resolves route/price, consistent with the other modules.)
     */
    protected function sendVotingResponse($text, $responseRoute, $source, $usersBigid)
    {
        if (trim((string) $text) === '') {
            return;
        }
        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2), (int) substr($datenow, 10, 2), (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2), (int) substr($datenow, 6, 2), (int) substr($datenow, 0, 4)
        );
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        DB::table('smsg_log')->insert([
            'sms_type' => 'sms', 'initiator' => 'iTagg', 'bigid' => $bigid,
            'mobnum' => $source, 'numparts' => $this->calculateSmsParts($text),
            'text' => urlencode($text), 'originator' => 'iTAGG', 'numbits' => 7,
            'timesubmitted' => $datenow, 'userref' => $usersBigid, 'affiliateref' => '0',
            'dosendtime' => $datenow, 'dosendtimeint' => $dosendtimeint, 'dayofyear' => substr($datenow, 0, 8),
            'timesent' => '00000000000000', 'sentstatus' => 'pending', 'sentstatustmp' => 'pending',
            'sentstatustext' => 'iTAGG Voting', 'suppliermsgref' => '', 'smsgdaemonid' => $daemonId,
            'sendpriority' => $baseDaemonId, 'costprice' => 0.000000, 'userprice' => 0.000000,
            'aggregator_dlrcode' => 0, 'aggregator_dlrmsg' => 'Queued', 'campaignref' => '',
            'binaryflags' => '', 'profit' => 0.000000,
            'countrydialcode' => substr($source, 0, 2) == '44' ? '44' : '',
            'suppliername' => '', 'supplierrouteref' => '',
            'requested_route' => is_numeric($responseRoute) ? (int) $responseRoute : 0,
            'requested_routetag' => '', 'deliverystatus2' => 'pending', 'migration_flag' => 'new',
        ]);
        $this->log("Voting: response queued to $source");
    }

    /**
     * Execute SMS Forwarder module
     */
    protected function executeSmsForwarder($keyword, $subkeyword, $shortcodeNumber, $source, $message, $itaggId, $usersBigid)
    {
        $smsForwarder = DB::table('itagg_module_SMSForwarder')
            ->where('keyword', $keyword)
            ->where('subkeyword', $subkeyword ?: '')
            ->where('smsshortcode_number', $shortcodeNumber)
            ->first();

        if (!$smsForwarder || empty($smsForwarder->fwd_mobile)) {
            $this->log("SMS Forwarder: No configuration found");
            return;
        }

        $forwardNumbers = explode(',', $smsForwarder->fwd_mobile);
        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $forwardMessage = "From: $source\n$message";

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2),
            (int) substr($datenow, 10, 2),
            (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2),
            (int) substr($datenow, 6, 2),
            (int) substr($datenow, 0, 4)
        );

        // Daemon priority
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        foreach ($forwardNumbers as $number) {
            $number = trim($number);
            if (empty($number)) continue;

            $this->log("SMS Forwarder: Forwarding to $number");

            DB::table('smsg_log')->insert([
                'sms_type' => 'sms',
                'initiator' => 'iTagg-Forwarder',
                'bigid' => $bigid,
                'mobnum' => $number,
                'numparts' => $this->calculateSmsParts($forwardMessage),
                'text' => urlencode($forwardMessage),
                'originator' => $shortcodeNumber,
                'numbits' => 7,
                'timesubmitted' => $datenow,
                'userref' => $usersBigid,
                'affiliateref' => '0',
                'dosendtime' => $datenow,
                'dosendtimeint' => $dosendtimeint,
                'dayofyear' => substr($datenow, 0, 8),
                'timesent' => '00000000000000',
                'sentstatus' => 'pending',
                'sentstatustmp' => 'pending',
                'sentstatustext' => 'SMS Forwarder',
                'suppliermsgref' => '',
                'smsgdaemonid' => $daemonId,
                'sendpriority' => $baseDaemonId,
                'costprice' => 0.000000,
                'userprice' => 0.000000,
                'aggregator_dlrcode' => 0,
                'aggregator_dlrmsg' => 'Queued',
                'campaignref' => '',
                'binaryflags' => '',
                'profit' => 0.000000,
                'countrydialcode' => substr($number, 0, 2) == '44' ? '44' : '',
                'suppliername' => '',
                'supplierrouteref' => '',
                'requested_route' => 0,
                'requested_routetag' => '',
                'deliverystatus2' => 'pending',
                'migration_flag' => 'new',
            ]);
        }
    }

    /**
     * Execute Subscription module
     */
    protected function executeSubscription($keyword, $subkeyword, $shortcodeNumber, $source, $message, $rest, $itaggId, $usersBigid)
    {
        // Strict subkeyword scoping (old SubscriptionBase: NULL when empty else exact match).
        $q = DB::table('itagg_module_Subscription')->where('itagg_id', $itaggId);
        if ($subkeyword === null || $subkeyword === '') {
            $q->whereNull('subkeyword');
        } else {
            $q->where('subkeyword', $subkeyword);
        }
        $subscription = $q->first();

        if (!$subscription) {
            $this->log("Subscription: No configuration found");
            return;
        }

        $action = strtolower(trim($rest ?: $message));

        if (in_array($action, ['stop', 'unsubscribe', 'cancel', 'quit'], true)) {
            $this->handleSubscriptionUnsubscribe($subscription, $source, $usersBigid);
        } else {
            $this->handleSubscriptionSubscribe($subscription, $source, $usersBigid);
        }
    }

    /**
     * Members live in the legacy CP address-book join (the invented _members/_data
     * tables never existed): cp_users_groups -> cp_group_addressbook ->
     * cp_users_addressbook -> itagg_mobiledetail. Matches old SubscriptionBase.
     */
    protected function handleSubscriptionSubscribe($subscription, $source, $usersBigid)
    {
        $groupId = $this->resolveSubscriptionGroupId($subscription, $usersBigid, true);
        if (!$groupId) {
            $this->log("Subscription: no group_name/group for config {$subscription->id}");
            return;
        }

        $addressbookId = $this->ensureAddressbookEntry($source, $usersBigid);

        // Already a member?
        $already = DB::table('cp_group_addressbook')
            ->where('group_id', $groupId)->where('addressbook_id', $addressbookId)->exists();
        if ($already) {
            $this->log("Subscription: $source already subscribed to group $groupId");
            return;
        }

        // Max-subscribers gate -> send fail_response like the old system.
        if (!empty($subscription->max_subscribers) && $subscription->max_subscribers > 0) {
            $count = DB::table('cp_group_addressbook')->where('group_id', $groupId)->count();
            if ($count >= $subscription->max_subscribers) {
                $this->log("Subscription: max subscribers reached for group $groupId");
                if (!empty($subscription->fail_response)) {
                    $this->queueSubscriptionResponse($subscription->fail_response, $source, $usersBigid, $subscription->fail_route ?? null);
                }
                return;
            }
        }

        DB::table('cp_group_addressbook')->insert([
            'group_id' => $groupId,
            'addressbook_id' => $addressbookId,
        ]);
        $this->log("Subscription: $source subscribed to group $groupId");

        if (!empty($subscription->subscribe_response)) {
            $this->queueSubscriptionResponse($subscription->subscribe_response, $source, $usersBigid, $subscription->subscribe_route ?? null);
        }
    }

    protected function handleSubscriptionUnsubscribe($subscription, $source, $usersBigid)
    {
        $groupId = $this->resolveSubscriptionGroupId($subscription, $usersBigid, false);
        if (!$groupId) {
            $this->log("Subscription: no group for config {$subscription->id}");
            return;
        }

        $addressbookId = DB::table('cp_users_addressbook')
            ->join('itagg_mobiledetail', 'cp_users_addressbook.itagg_mobiledetail_id', '=', 'itagg_mobiledetail.id')
            ->where('cp_users_addressbook.user_bigid', $usersBigid)
            ->where('itagg_mobiledetail.msisdn', $source)
            ->value('cp_users_addressbook.id');

        if (!$addressbookId) {
            $this->log("Subscription: $source not found in address book");
            return;
        }

        $deleted = DB::table('cp_group_addressbook')
            ->where('group_id', $groupId)->where('addressbook_id', $addressbookId)->delete();

        if ($deleted) {
            $this->log("Subscription: $source unsubscribed from group $groupId");
            if (!empty($subscription->unsubscribe_response)) {
                $this->queueSubscriptionResponse($subscription->unsubscribe_response, $source, $usersBigid, $subscription->unsubscribe_route ?? null);
            }
        } else {
            $this->log("Subscription: $source was not subscribed to group $groupId");
        }
    }

    /**
     * Resolve cp_users_groups.id for this subscription's group_name (+ owner).
     * Optionally create the group row on first subscribe.
     */
    protected function resolveSubscriptionGroupId($subscription, $usersBigid, $createIfMissing)
    {
        $groupName = $subscription->group_name ?? null;
        if (empty($groupName)) {
            return null;
        }
        $groupId = DB::table('cp_users_groups')
            ->where('name', $groupName)->where('user_bigid', $usersBigid)->value('id');
        if (!$groupId && $createIfMissing) {
            $groupId = DB::table('cp_users_groups')->insertGetId([
                'name' => $groupName,
                'user_bigid' => $usersBigid,
            ]);
        }
        return $groupId ?: null;
    }

    /**
     * Ensure itagg_mobiledetail + cp_users_addressbook rows exist for a number
     * (for this owner); return the cp_users_addressbook.id used as the member key.
     */
    protected function ensureAddressbookEntry($msisdn, $usersBigid)
    {
        $mobiledetailId = DB::table('itagg_mobiledetail')->where('msisdn', $msisdn)->value('id');
        if (!$mobiledetailId) {
            $mobiledetailId = DB::table('itagg_mobiledetail')->insertGetId([
                'msisdn' => $msisdn,
                'net_id' => (int) app(\App\Services\TableCache::class)->ofcomNetId($msisdn),
                'confirmed' => 0,
            ]);
        }

        $addressbookId = DB::table('cp_users_addressbook')
            ->where('user_bigid', $usersBigid)
            ->where('itagg_mobiledetail_id', $mobiledetailId)
            ->value('id');
        if (!$addressbookId) {
            $addressbookId = DB::table('cp_users_addressbook')->insertGetId([
                'name' => $msisdn,
                'itagg_mobiledetail_id' => $mobiledetailId,
                'user_bigid' => $usersBigid,
                'is_favourite' => 'n',
            ]);
        }
        return $addressbookId;
    }

    protected function queueSubscriptionResponse($response, $source, $usersBigid, $routeId)
    {
        $responseText = urldecode($response);
        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2),
            (int) substr($datenow, 10, 2),
            (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2),
            (int) substr($datenow, 6, 2),
            (int) substr($datenow, 0, 4)
        );

        // Daemon priority
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        DB::table('smsg_log')->insert([
            'sms_type' => 'sms',
            'initiator' => 'Subscription',
            'bigid' => $bigid,
            'mobnum' => $source,
            'numparts' => $this->calculateSmsParts($responseText),
            'text' => $response,
            'originator' => 'iTagg',
            'numbits' => 7,
            'timesubmitted' => $datenow,
            'userref' => $usersBigid,
            'affiliateref' => '0',
            'dosendtime' => $datenow,
            'dosendtimeint' => $dosendtimeint,
            'dayofyear' => substr($datenow, 0, 8),
            'timesent' => '00000000000000',
            'sentstatus' => 'pending',
            'sentstatustmp' => 'pending',
            'sentstatustext' => 'Subscription Response',
            'suppliermsgref' => '',
            'smsgdaemonid' => $daemonId,
            'sendpriority' => $baseDaemonId,
            'costprice' => 0.000000,
            'userprice' => 0.000000,
            'aggregator_dlrcode' => 0,
            'aggregator_dlrmsg' => 'Queued',
            'campaignref' => '',
            'binaryflags' => '',
            'profit' => 0.000000,
            'countrydialcode' => substr($source, 0, 2) == '44' ? '44' : '',
            'suppliername' => '',
            'supplierrouteref' => '',
            'requested_route' => 0,
            'requested_routetag' => '',
            'deliverystatus2' => 'pending',
            'migration_flag' => 'new',
        ]);
    }

    /**
     * Execute WAP Push Responder module
     */
    protected function executeWapPushResponder($keyword, $subkeyword, $shortcodeNumber, $source, $itaggId, $usersBigid)
    {
        $wapPush = DB::table('itagg_module_WAPPushResponder')
            ->where('keyword', $keyword)
            ->where('subkeyword', $subkeyword ?: '')
            ->where('smsshortcode_number', $shortcodeNumber)
            ->first();

        if (!$wapPush || empty($wapPush->url)) {
            $this->log("WAP Push Responder: No configuration found or URL not set");
            return;
        }

        $title = $wapPush->title ?? 'Link';
        $url = $wapPush->url;

        $this->log("WAP Push Responder: Sending '$title' -> $url to $source");

        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $wapMessage = "$title: $url";

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2),
            (int) substr($datenow, 10, 2),
            (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2),
            (int) substr($datenow, 6, 2),
            (int) substr($datenow, 0, 4)
        );

        // Daemon priority
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        DB::table('smsg_log')->insert([
            'sms_type' => 'wap',
            'initiator' => 'WAP-Push',
            'bigid' => $bigid,
            'mobnum' => $source,
            'numparts' => 1,
            'text' => urlencode($wapMessage),
            'originator' => $shortcodeNumber,
            'numbits' => 7,
            'timesubmitted' => $datenow,
            'userref' => $usersBigid,
            'affiliateref' => '0',
            'dosendtime' => $datenow,
            'dosendtimeint' => $dosendtimeint,
            'dayofyear' => substr($datenow, 0, 8),
            'timesent' => '00000000000000',
            'sentstatus' => 'pending',
            'sentstatustmp' => 'pending',
            'sentstatustext' => 'WAP Push',
            'suppliermsgref' => '',
            'smsgdaemonid' => $daemonId,
            'sendpriority' => $baseDaemonId,
            'costprice' => 0.000000,
            'userprice' => 0.000000,
            'aggregator_dlrcode' => 0,
            'aggregator_dlrmsg' => 'Queued',
            'campaignref' => '',
            'binaryflags' => '',
            'profit' => 0.000000,
            'countrydialcode' => substr($source, 0, 2) == '44' ? '44' : '',
            'suppliername' => '',
            'supplierrouteref' => '',
            'requested_route' => 0,
            'requested_routetag' => '',
            'deliverystatus2' => 'pending',
            'migration_flag' => 'new',
        ]);
    }

    /**
     * Execute Business Card module
     */
    protected function executeBusinessCard($itaggId, $subkeyword, $source, $usersBigid, $incomingLogId)
    {
        $businessCard = DB::table('itagg_module_BusinessCard')
            ->where('itagg_id', $itaggId)
            ->where('subkeyword', $subkeyword ?: '')
            ->first();

        if (!$businessCard) {
            $this->log("Business Card: No configuration found");
            return;
        }

        $this->log("Business Card: Building vCard for " . ($businessCard->firstname ?? '') . " " . ($businessCard->lastname ?? ''));

        // Build vCard content
        $crlf = chr(13) . chr(10);
        $vcardBody = $this->hexEncode("BEGIN:VCARD" . $crlf);
        $vcardBody .= $this->hexEncode("VERSION:2.1" . $crlf);

        if (!empty($businessCard->firstname) || !empty($businessCard->lastname)) {
            $vcardBody .= $this->hexEncode("N:" . ($businessCard->lastname ?? '') . ";" . ($businessCard->firstname ?? '') . $crlf);
        }

        if (!empty($businessCard->organisation)) {
            $vcardBody .= $this->hexEncode("ORG:" . $businessCard->organisation . $crlf);
        }

        if (!empty($businessCard->title)) {
            $vcardBody .= $this->hexEncode("TITLE:" . $businessCard->title . $crlf);
        }

        $formattedAddress = implode(';', [
            $businessCard->pobox ?? '',
            $businessCard->extended ?? '',
            $businessCard->street ?? '',
            $businessCard->locality ?? '',
            $businessCard->region ?? '',
            $businessCard->postcode ?? '',
            $businessCard->country ?? ''
        ]);
        $vcardBody .= $this->hexEncode("ADR;WORK:" . $formattedAddress . $crlf);

        if (!empty($businessCard->tel_work)) {
            $vcardBody .= $this->hexEncode("TEL;WORK;VOICE:" . $businessCard->tel_work . $crlf);
        }
        if (!empty($businessCard->tel_home)) {
            $vcardBody .= $this->hexEncode("TEL;HOME;VOICE:" . $businessCard->tel_home . $crlf);
        }
        if (!empty($businessCard->tel_mobile)) {
            $vcardBody .= $this->hexEncode("TEL;CELL;VOICE:" . $businessCard->tel_mobile . $crlf);
        }
        if (!empty($businessCard->fax)) {
            $vcardBody .= $this->hexEncode("TEL;WORK;FAX:" . $businessCard->fax . $crlf);
        }
        if (!empty($businessCard->website)) {
            $vcardBody .= $this->hexEncode("URL:" . $businessCard->website . $crlf);
        }
        if (!empty($businessCard->email)) {
            $vcardBody .= $this->hexEncode("EMAIL:" . $businessCard->email . $crlf);
        }

        $vcardBody .= $this->hexEncode("END:VCARD" . $crlf);

        // Split into parts
        $maxLen = 140 - 12;
        $parts = [];

        while (strlen($vcardBody) > ($maxLen * 3)) {
            $parts[] = substr($vcardBody, 0, $maxLen * 3);
            $vcardBody = substr($vcardBody, $maxLen * 3);
        }
        $parts[] = $vcardBody;

        $totalParts = count($parts);
        $this->log("Business Card: vCard split into $totalParts parts");

        $bigid = md5(uniqid(rand(), true));
        $datenow = Carbon::now('Europe/London')->format('YmdHis');
        $senderId = $businessCard->sender_id ?? 'vCard';

        // Calculate dosendtimeint as Unix timestamp (like old system)
        $dosendtimeint = mktime(
            (int) substr($datenow, 8, 2),
            (int) substr($datenow, 10, 2),
            (int) substr($datenow, 12, 2),
            (int) substr($datenow, 4, 2),
            (int) substr($datenow, 6, 2),
            (int) substr($datenow, 0, 4)
        );

        // Daemon priority
        $baseDaemonId = 100;
        $daemonId = $baseDaemonId + mt_rand(0, 39);

        for ($i = 1; $i <= $totalParts; $i++) {
            $udh = sprintf(":0B:05:04:23:F4:00:00:00:03:00:%02X:%02X", $totalParts, $i);
            $binaryText = $udh . "-" . $parts[$i - 1];

            DB::table('smsg_log')->insert([
                'sms_type' => 'binary',
                'initiator' => 'BusinessCard',
                'bigid' => $bigid,
                'mobnum' => $source,
                'numparts' => $totalParts,
                'text' => urlencode($binaryText),
                'originator' => $senderId,
                'numbits' => 7,
                'timesubmitted' => $datenow,
                'userref' => $usersBigid,
                'affiliateref' => '0',
                'dosendtime' => $datenow,
                'dosendtimeint' => $dosendtimeint,
                'dayofyear' => substr($datenow, 0, 8),
                'timesent' => '00000000000000',
                'sentstatus' => 'pending',
                'sentstatustmp' => 'pending',
                'sentstatustext' => 'vCard Business Card',
                'suppliermsgref' => '',
                'smsgdaemonid' => $daemonId,
                'sendpriority' => $baseDaemonId,
                'costprice' => 0.000000,
                'userprice' => 0.000000,
                'aggregator_dlrcode' => 0,
                'aggregator_dlrmsg' => 'Queued',
                'campaignref' => '',
                'binaryflags' => 'vcard',
                'profit' => 0.000000,
                'countrydialcode' => substr($source, 0, 2) == '44' ? '44' : '',
                'suppliername' => '',
                'supplierrouteref' => '',
                'requested_route' => 0,
                'requested_routetag' => '',
                'deliverystatus2' => 'pending',
                'migration_flag' => 'new',
            ]);
        }

        $this->log("Business Card: vCard queued successfully");
    }

    protected function hexEncode($content)
    {
        $output = '';
        for ($i = 0; $i < strlen($content); $i++) {
            $output .= ':' . str_pad(strtoupper(dechex(ord(substr($content, $i, 1)))), 2, '0', STR_PAD_LEFT);
        }
        return $output;
    }

    protected function logSlowProcess($moduleId, $timeTaken, $keyword, $subkeyword, $dest, $incomingLogId, $moduleName, $source, $message, $network)
    {
        $description = "Module $moduleName. source = $source, message = $message, network = $network";
        
        DB::table('itagg_incominglog_slowprocesses')->insert([
            'module_id' => $moduleId,
            'time_taken' => $timeTaken,
            'keyword' => $keyword,
            'subkeyword' => $subkeyword,
            'dest' => $dest,
            'incominglog_id' => $incomingLogId,
            'description' => $description,
            'time_logged' => Carbon::now()->format('YmdHis')
        ]);

        $this->log("⚠️ Slow process logged: Module $moduleId took {$timeTaken} seconds");
    }

    protected function calculateSmsParts($message)
    {
        $length = mb_strlen($message, 'UTF-8');
        if ($length <= 160) return 1;
        return (int) ceil($length / 153);
    }

    protected function decodeMessage($message)
    {
        return str_replace(
            ['&apos;', '&amp;', '&quot;', '&lt;', '&gt;'],
            ["'", '&', '"', '<', '>'],
            $message
        );
    }

    protected function parseMessage($message)
    {
        $message = trim($message);
        $parts = preg_split('/[\s]+/', $message, 4, PREG_SPLIT_NO_EMPTY);

        return [
            'keyword' => $parts[0] ?? '',
            'subkeyword' => $parts[1] ?? '',
            'rest' => isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : '',
            'parts' => $parts
        ];
    }

    protected function processAliasRules($keyword, $subkeyword, $dest, $originalMessage)
    {
        $today = Carbon::now()->format('Y-m-d');

        if ($subkeyword) {
            $alias = DB::table('itagg_alias_rules')
                ->join('itagg_subalias', 'itagg_subalias.id', '=', 'itagg_alias_rules.itagg_subalias_id')
                ->join('itagg_alias', 'itagg_subalias.itagg_alias_id', '=', 'itagg_alias.id')
                ->join('smsshortcodes', 'itagg_alias.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->leftJoin('itagg_instance', 'itagg_alias_rules.itagg_instance_id', '=', 'itagg_instance.id')
                ->where('itagg_alias.keyword', $keyword)
                ->where('itagg_subalias.subalias', $subkeyword)
                ->where('smsshortcodes.number', $dest)
                ->where('itagg_alias_rules.expiry', '>', $today)
                ->where('itagg_alias.expiry', '>', $today)
                ->where('itagg_subalias.expiry', '>', $today)
                ->where('itagg_alias_rules.active', 1)
                ->where('itagg_alias.active', 1)
                ->where('itagg_subalias.active', 1)
                ->select('itagg_instance.keyword', 'itagg_alias_rules.new_subkeyword', 'itagg_instance.id')
                ->first();

            if ($alias) {
                $messageParts = $this->parseMessage($originalMessage);
                $newMessage = $alias->keyword;
                if ($alias->new_subkeyword) {
                    $newMessage .= ' ' . $alias->new_subkeyword;
                }
                $newMessage .= ' ' . ($messageParts['rest'] ?? '');

                return ['transformed' => true, 'new_message' => trim($newMessage)];
            }
        }

        $alias = DB::table('itagg_alias_rules')
            ->join('itagg_alias', 'itagg_alias.id', '=', 'itagg_alias_rules.itagg_alias_id')
            ->join('smsshortcodes', 'itagg_alias.smsshortcodes_id', '=', 'smsshortcodes.id')
            ->leftJoin('itagg_instance', 'itagg_alias_rules.itagg_instance_id', '=', 'itagg_instance.id')
            ->where('itagg_alias.keyword', $keyword)
            ->where('smsshortcodes.number', $dest)
            ->where('itagg_alias_rules.expiry', '>', $today)
            ->where('itagg_alias.expiry', '>', $today)
            ->where('itagg_alias_rules.active', 1)
            ->where('itagg_alias.active', 1)
            ->select('itagg_instance.keyword', 'itagg_alias_rules.new_subkeyword', 'itagg_instance.id')
            ->first();

        if ($alias) {
            $messageParts = $this->parseMessage($originalMessage);
            $newMessage = $alias->keyword;
            if ($alias->new_subkeyword) {
                $newMessage .= ' ' . $alias->new_subkeyword;
            }
            $newMessage .= ' ' . $subkeyword . ' ' . ($messageParts['rest'] ?? '');

            return ['transformed' => true, 'new_message' => trim($newMessage)];
        }

        return ['transformed' => false];
    }

    protected function findItaggInstance($keyword, $dest)
    {
        $shortcode = SmsShortcode::where('number', $dest)->first();
        if (!$shortcode) {
            $this->log("Shortcode not found: $dest");
            return null;
        }

        $gracePeriodDate = Carbon::now()->subDays(self::GRACE_PERIOD)->format('Y-m-d');

        return DB::table('itagg_instance')
            ->where('smsshortcodes_id', $shortcode->id)
            ->where(function ($query) use ($keyword) {
                $query->where('keyword', $keyword)
                    ->orWhere('keyword', '*');
            })
            ->where('active', 1)
            ->where('expiry', '>', $gracePeriodDate)
            ->orderByRaw("if(keyword = '*', 0, 1) desc")
            ->first();
    }

    protected function isStopCommand($message)
    {
        $cleaned = urldecode(str_replace(['+', '#', '*', '_', '-', ' '], '', strtoupper(trim($message))));
        return in_array($cleaned, ['STOP', 'STOPALL']);
    }

    protected function handleStopCommand($source, $dest, $message, $usersBigid, $mobileDetailId, $incomingLogId)
    {
        $user = User::where('bigid', $usersBigid)->first();
        $cleaned = urldecode(str_replace(['+', '#', '*', '_', '-', ' '], '', strtoupper(trim($message))));
        
        $previousStops = DB::table('itagg_inbound_stopcommands')
            ->where('msisdn', $source)
            ->where('dest', $dest)
            ->count();

        $commandType = ($previousStops > 0 || $cleaned == 'STOPALL') ? 'STOP ALL' : 'STOP';
        $this->log("Processing $commandType command for $source");

        $existingStopCommand = DB::table('itagg_inbound_stopcommands')
            ->where('msisdn', $source)
            ->where('dest', $dest)
            ->first();

        $stopCommandData = [
            'itagg_mobile_detail_id' => $mobileDetailId,
            'users_bigid' => $usersBigid,
            'is_internal' => 1,
            'msisdn' => $source,
            'dest' => $dest,
            'inbound_date' => now()
        ];

        if ($existingStopCommand) {
            DB::table('itagg_inbound_stopcommands')
                ->where('msisdn', $source)
                ->where('dest', $dest)
                ->update($stopCommandData);
            $this->log("✅ Updated STOP command record");
        } else {
            DB::table('itagg_inbound_stopcommands')->insert($stopCommandData);
            $this->log("✅ Inserted new STOP command record");
        }

        $existingBlacklist = DB::table('itagg_outbound_blacklist')
            ->where('users_bigid', $usersBigid)
            ->where('msisdn', $source)
            ->first();

        $blacklistData = [
            'users_bigid' => $usersBigid,
            'msisdn' => $source,
            'date_blocked' => now(),
            'stop_or_stopall' => strtolower($commandType),
            'itagg_mobile_detail_id' => $mobileDetailId,
            'users_uname' => $user ? $user->uname : null,
        ];

        if ($existingBlacklist) {
            DB::table('itagg_outbound_blacklist')
                ->where('users_bigid', $usersBigid)
                ->where('msisdn', $source)
                ->update($blacklistData);
            $this->log("✅ Updated blacklist record");
        } else {
            DB::table('itagg_outbound_blacklist')->insert($blacklistData);
            $this->log("✅ Inserted new blacklist record");
        }

        if ($user && $user->contactemail) {
            $this->sendStopNotification($user, $source, $dest, $commandType);
        }

        $this->removeFromGroups($source, $usersBigid);

        $this->log("🚫 STOP command processing complete");
    }

    protected function handleUnmatchedStopCommand($source, $dest, $message, $network, $operatorMessageId, $operatorId, $mobileDetailId)
    {
        $this->log('Handling unmatched STOP command');

        $recentSender = DB::table('smsg_log')
            ->join('users', 'smsg_log.userref', '=', 'users.bigid')
            ->where('smsg_log.mobnum', $source)
            ->where('smsg_log.migration_flag', 'new')
            ->orderBy('smsg_log.timesent', 'desc')
            ->select('users.bigid', 'users.contactemail', 'users.contactname', 'users.uname')
            ->first();

        if ($recentSender) {
            $usersBigid = $recentSender->bigid;
            $this->mostrecentuser = $usersBigid;

            $this->logIncoming('', '', $source, $dest, $message, $network, 0, $usersBigid, $operatorMessageId, $operatorId);

            $cleaned = urldecode(str_replace(['+', '#', '*', '_', '-', ' '], '', strtoupper(trim($message))));
            $commandType = ($cleaned == 'STOPALL') ? 'STOP ALL' : 'STOP';

            $existingStopCommand = DB::table('itagg_inbound_stopcommands')
                ->where('msisdn', $source)
                ->where('dest', $dest)
                ->first();

            $stopCommandData = [
                'itagg_mobile_detail_id' => $mobileDetailId,
                'users_bigid' => $usersBigid,
                'is_internal' => 1,
                'msisdn' => $source,
                'dest' => $dest,
                'inbound_date' => now()
            ];

            if ($existingStopCommand) {
                DB::table('itagg_inbound_stopcommands')
                    ->where('msisdn', $source)
                    ->where('dest', $dest)
                    ->update($stopCommandData);
            } else {
                DB::table('itagg_inbound_stopcommands')->insert($stopCommandData);
            }

            $existingBlacklist = DB::table('itagg_outbound_blacklist')
                ->where('users_bigid', $usersBigid)
                ->where('msisdn', $source)
                ->first();

            $blacklistData = [
                'users_bigid' => $usersBigid,
                'msisdn' => $source,
                'date_blocked' => now(),
                'stop_or_stopall' => strtolower($commandType),
                'itagg_mobile_detail_id' => $mobileDetailId,
                'users_uname' => $recentSender->uname ?? null,
            ];

            if ($existingBlacklist) {
                DB::table('itagg_outbound_blacklist')
                    ->where('users_bigid', $usersBigid)
                    ->where('msisdn', $source)
                    ->update($blacklistData);
            } else {
                DB::table('itagg_outbound_blacklist')->insert($blacklistData);
            }

            $this->log("✅ Processed unmatched STOP command");
        } else {
            $this->logIncoming('', '', $source, $dest, $message, $network, 0, self::NO_BIGID, $operatorMessageId, $operatorId);
            $this->log("⚠️ No previous sender found for unmatched STOP");
        }
    }

    protected function removeFromBlacklist($source, $usersBigid, $dest)
    {
        $deletedBlacklist = DB::table('itagg_outbound_blacklist')
            ->where('users_bigid', $usersBigid)
            ->where('msisdn', $source)
            ->delete();

        $deletedStopCommands = DB::table('itagg_inbound_stopcommands')
            ->where('msisdn', $source)
            ->where('dest', $dest)
            ->delete();

        if ($deletedBlacklist || $deletedStopCommands) {
            $this->log("✅ Removed $source from blacklist (user re-engaged)");
        }
    }

    protected function updateMobileDetail($source, $network, $operatorId)
    {
        $existing = DB::table('itagg_mobiledetail')
            ->where('msisdn', $source)
            ->first();

        if ($existing) {
            DB::table('itagg_mobiledetail')
                ->where('msisdn', $source)
                ->update([
                    'net_id' => $network,
                    'confirmed' => 1,
                    'lastchanged' => now()->format('YmdHis'),
                    'mbloxDeliverer' => $operatorId
                ]);
            return $existing->id;
        }

        return DB::table('itagg_mobiledetail')->insertGetId([
            'msisdn' => $source,
            'net_id' => $network,
            'confirmed' => 1,
            'mbloxDeliverer' => $operatorId
        ]);
    }

    protected function getMobileDetailId($source)
    {
        $detail = DB::table('itagg_mobiledetail')
            ->where('msisdn', $source)
            ->first();

        return $detail ? $detail->id : null;
    }

    protected function manageUserGroupsAndAddressBook($keyword, $subkeyword, $source, $usersBigid, $mobileDetailId)
    {
        $groupName = 'iTAGG incoming ' . $keyword;
        if ($subkeyword) {
            $groupName .= ' ' . $subkeyword;
        }

        $group = DB::table('cp_users_groups')
            ->where('name', $groupName)
            ->where('user_bigid', $usersBigid)
            ->first();

        if (!$group) {
            $groupId = DB::table('cp_users_groups')->insertGetId([
                'name' => $groupName,
                'user_bigid' => $usersBigid
            ]);
        } else {
            $groupId = $group->id;
        }

        $addressBook = DB::table('cp_users_addressbook')
            ->where('itagg_mobiledetail_id', $mobileDetailId)
            ->where('user_bigid', $usersBigid)
            ->first();

        if (!$addressBook) {
            $addressBookId = DB::table('cp_users_addressbook')->insertGetId([
                'name' => $source,
                'itagg_mobiledetail_id' => $mobileDetailId,
                'user_bigid' => $usersBigid
            ]);
        } else {
            $addressBookId = $addressBook->id;
        }

        $link = DB::table('cp_group_addressbook')
            ->where('group_id', $groupId)
            ->where('addressbook_id', $addressBookId)
            ->exists();

        if (!$link) {
            DB::table('cp_group_addressbook')->insert([
                'group_id' => $groupId,
                'addressbook_id' => $addressBookId
            ]);
        }

        $this->log("✅ Managed groups and address book");
    }

    protected function removeFromGroups($source, $usersBigid)
    {
        $mobileDetailId = $this->getMobileDetailId($source);
        if (!$mobileDetailId) return;

        $addressBookIds = DB::table('cp_users_addressbook')
            ->where('itagg_mobiledetail_id', $mobileDetailId)
            ->where('user_bigid', $usersBigid)
            ->pluck('id');

        if ($addressBookIds->isEmpty()) return;

        DB::table('cp_group_addressbook')
            ->whereIn('addressbook_id', $addressBookIds)
            ->delete();

        DB::table('cp_users_addressbook')
            ->whereIn('id', $addressBookIds)
            ->delete();

        $this->log("✅ Removed from all groups");
    }

    protected function forwardToUrl($url, $source, $dest, $message, $keyword, $subkeyword, $rest, $incomingLogId, $forwardingLogId, $itaggId, $usersBigid, $network = '99', $operatorMessageId = null)
    {
        $userOptions = DB::table('useroption')
            ->where('userref', $usersBigid)
            ->first();

        $numRetries = $userOptions->urlfwd_num_retries ?? 3;
        $waitMinutes = $userOptions->urlfwd_wait_minutes ?? 60;

        $itaggSettings = DB::table('itagg_instance')
            ->where('id', $itaggId)
            ->select('urlforward_daemonname', 'urlforward_timeout_seconds')
            ->first();

        $daemonName = $itaggSettings->urlforward_daemonname ?? '';
        $timeoutSeconds = $itaggSettings->urlforward_timeout_seconds ?? 30;

        $paramArr = [
            'source' => $source,
            'dest' => $dest,
            'message' => $message,
            'dtime' => Carbon::now()->format('YmdHis'),
            'network' => $network,               // real network id (old system sent the actual network, not a hardcoded 99)
            'message_after_match' => $rest,
            'matched_keyword' => $keyword,
            'matched_subkeyword' => $subkeyword,
        ];
        // Old system appends message_id only when an operator message id is present.
        if (!empty($operatorMessageId)) {
            $paramArr['message_id'] = $operatorMessageId;
        }
        $params = http_build_query($paramArr);

        $timeNow = Carbon::now()->format('Y-m-d H:i:s');

        DB::table('url_forward')->insert([
            'url' => $url,
            'inserted_time' => $timeNow,
            'retries_left' => $numRetries,
            'wait_minutes' => $waitMinutes,
            'dosendtime' => $timeNow,
            'itagg_incominglog_id' => $incomingLogId,
            'itagg_forwardinglog_id' => $forwardingLogId,
            'parameter_string' => $params,
            'daemonname' => $daemonName,
            'timeout_seconds' => $timeoutSeconds,
            'itagg_instance_id' => $itaggId
        ]);

        $this->log("📤 Queued URL forward to: $url");
    }

    protected function forwardToEmail($email, $source, $dest, $message, $keyword, $subkeyword, $rest, $network, $itaggId, $usersBigid)
    {
        $networkNames = [
            '30' => 'T Mobile',
            '10' => 'O2',
            '15' => 'Vodafone',
            '20' => '3UK',
            '33' => 'Orange'
        ];
        $networkName = $networkNames[$network] ?? 'Unknown';

        $recipients = preg_split('/[,;]/', $email);

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (empty($recipient)) continue;

            try {
                $smsData = [
                    'keyword' => $keyword,
                    'source' => $source,
                    'destination' => $dest,
                    'message' => $message,
                    'subkeyword' => $subkeyword,
                    'rest' => $rest,
                    'network_name' => $networkName,
                    'date' => Carbon::now()->format('l, jS F Y \a\t H:i'),
                    'subject' => "Someone has texted your SMS Keyword {$keyword} - from {$source}",
                ];

                $emailQueueService = new \App\Services\Queue\EmailQueueService();
                $emailQueueService->queueEmail(
                    'App\\Mail\\InboundSmsNotificationMail',
                    $recipient,
                    ['sms_data' => $smsData],
                    [],
                    5
                );

                $this->log("📧 Forwarded email queued to: $recipient");
            } catch (Exception $e) {
                $this->log("❌ Email forwarding failed: " . $e->getMessage());
            }
        }
    }

    protected function sendStopNotification($user, $source, $dest, $commandType)
    {
        try {
            $stopData = [
                'contact_name' => $user->contactname,
                'source' => $source,
                'destination' => $dest,
                'command_type' => $commandType,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'subject' => "iTAGG: {$commandType} to {$dest} - ACTION REQUIRED",
            ];

            $emailQueueService = new \App\Services\Queue\EmailQueueService();
            $emailQueueService->queueEmail(
                'App\\Mail\\StopNotificationMail',
                $user->contactemail,
                ['stop_data' => $stopData],
                [],
                10 // High priority
            );

            $this->log("📧 STOP notification queued");
        } catch (Exception $e) {
            $this->log("❌ Failed to send STOP notification: " . $e->getMessage());
        }
    }

    protected function logIncoming($keyword, $subkeyword, $source, $dest, $message, $network, $matched, $usersBigid, $operatorMessageId, $operatorId)
    {
        $data = [
            'recieved' => now(),
            'source' => $source,
            'dest' => $dest,
            'keyword' => $keyword,
            'subkeyword' => $subkeyword,
            'msg' => urlencode($message),
            'network' => $network,
            'matched' => $matched,
            'user_bigid' => $usersBigid,
            'mobile_client_bigid' => '',
            'mobile_client_type' => '',
            'mobile_client_version' => '',
            'viewed_by_java_desktop' => 0,
            'msisdnAlias' => '',
            'mbloxDeliverer' => $operatorId
        ];

        if ($operatorMessageId != self::NO_OPERATOR_MESSAGE_ID) {
            $data['operator_message_id'] = $operatorMessageId;
        }

        $id = DB::table('itagg_incominglog')->insertGetId($data);
        $this->log("📝 Logged incoming SMS - ID: $id");

        return $id;
    }

    protected function log($message)
    {
        $this->debugLog[] = $message;
        if ($this->debug) {
            Log::info('[InboundSMS:' . $this->requestId . '] ' . $message);
        }
    }

    public function getDebugLog()
    {
        return implode("\n", $this->debugLog);
    }
}
