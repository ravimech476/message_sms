<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SmsSendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;


/**
 * Legacy SMS API Controller
 * 
 * Provides backwards-compatible API endpoint matching the old PHP system:
 * https://secure.devsmsexpert.com/smsg/sms.mes?usr=XXX&pwd=YYY&from=myname&to=447912345678,447987654321&type=text&route=d&txt=helloworld
 * 
 * Response Format:
 * error code|error text|submission reference
 * 0|sms submitted|bigid-servernum
 */
class LegacySmsApiController extends Controller
{
    protected $smsSendingService;
    
    public function __construct(SmsSendingService $smsSendingService)
    {
        $this->smsSendingService = $smsSendingService;
    }
    
    /**
     * Handle SMS sending request (GET or POST)
     * 
     * Query Parameters:
     * - usr: Username (required)
     * - pwd: Password (required)
     * - from: Sender ID (required)
     * - to: Recipients, comma-separated (required)
     * - txt: Message text (required)
     * - type: text, binary, unicode, longmessage (default: text)
     * - route: d, p, e, s, g, l, or numeric (default: d)
     * - send: Scheduled send time YmdHis format (optional)
     * - dreceipt_url: Delivery receipt callback URL (optional)
     * - userdefined: User-defined data (optional)
     * - campaignref: Campaign reference (optional)
     * - itaggsecretkey: Alternative auth via secret key (optional)
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function sendSms(Request $request)
    {
        // Get parameters from query string (GET) or form data (POST)

        // Get user info for bulk throughput validation
        $userInfo = $request->input('usr'); 

        // Get user ID from users table for blacklist check
        $getUserId = User::where('uname', $userInfo)->first();

        // OLD SYSTEM: $send = now + offset when `sendrelative` (seconds) is supplied
        // and `send` is not. Matches sms.mes line ~65.
        $send = $request->input('send', '');
        $sendrelative = $request->input('sendrelative', '');
        if ($send === '' && $sendrelative !== '' && is_numeric($sendrelative)) {
            $send = Carbon::now('Europe/London')->addSeconds((int) $sendrelative)->format('YmdHis');
        }

        $params = [
            'usr' => $request->input('usr', ''),
            'pwd' => $request->input('pwd', ''),
            'userbigid' => $request->input('userbigid', ''),
            'itaggsecretkey' => $request->input('itaggsecretkey', ''),
            'smppusr' => $request->input('smppusr', ''),                 // OLD: SMPP front-end user
            'subusrkey' => $request->input('subusrkey', ''),             // OLD: pooled virtual-number sub-account key
            'from' => $request->input('from', ''),
            'to' => $request->input('to', ''),
            'txt' => $request->input('txt', ''),
            'type' => $request->input('type', 'text'),
            'route' => $request->input('route', 'd'),
            'send' => $send,
            'dreceipt_url' => $request->input('dreceipt_url', ''),
            // OLD param name is `userdef`; accept both (`userdefined` wins if both sent)
            'userdefined' => $request->input('userdefined', $request->input('userdef', '')),
            'campaignref' => $request->input('campaignref', ''),
            'userdefinedSubRef' => $request->input('userdefinedSubRef', ''),
            'sitype' => $request->input('sitype', ''),
            'binaryflags' => $request->input('binaryflags', ''),          // OLD: binary message flags
            'incoming_message_id' => $request->input('incoming_message_id', '0'), // OLD: link outbound->inbound MO
            'msisdnAlias' => $request->input('msisdnAlias', ''),          // OLD: alias->msisdn (location fwd)
            'initiator' => 'API',
            // Rows sent through THIS (new) system are always tagged 'new' so the
            // new-system DLR / delivery-status / webhook / reporting pipeline processes
            // them (e.g. DeliveryStatusService skips rows where migration_flag != 'new').
            // The user's own migration_flag is a routing/cutover control, NOT the owner
            // of a row the new system just inserted — copying it (default 'old') orphaned
            // these sends from DLR processing, leaving delivery status stuck 'pending'.
            'migration_flag' => 'new',
        ];
        
        Log::info('Legacy SMS API Request', [
            'usr' => $params['usr'],
            'from' => $params['from'],
            'to_count' => count(explode(',', $params['to'])),
            'type' => $params['type'],
            'route' => $params['route'],
            'ip' => $request->ip()
        ]);
        
        // Send SMS using the service
        $result = $this->smsSendingService->sendSms($params);
        
        // Return plain text response in legacy format
        return response($result['response'], 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
    
    /**
     * Handle Service Indicator (WAP Push) request
     * 
     * Additional Parameters:
     * - sitype: url or vcard (required)
     * 
     * For sitype=url, txt format should be: title|url
     */
    public function sendSi(Request $request)
    {
        $params = [
            'usr' => $request->input('usr', ''),
            'pwd' => $request->input('pwd', ''),
            'userbigid' => $request->input('userbigid', ''),
            'itaggsecretkey' => $request->input('itaggsecretkey', ''),
            'from' => $request->input('from', ''),
            'to' => $request->input('to', ''),
            'txt' => $request->input('txt', ''),
            'type' => 'binary',
            'route' => $request->input('route', 'd'),
            'send' => $request->input('send', ''),
            'dreceipt_url' => $request->input('dreceipt_url', ''),
            'userdefined' => $request->input('userdefined', ''),
            'sitype' => $request->input('sitype', ''),
            'initiator' => 'API',
        ];
        
        // Validate sitype
        $allowedSITypes = ['url', 'vcard'];
        if (empty($params['sitype']) || !in_array($params['sitype'], $allowedSITypes)) {
            $response = "error code|error text|submission reference\n";
            $response .= "200|SI submission failed - sitype not set or invalid|0\n";
            return response($response, 200)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }
        
        // For URL type, validate and convert text
        if ($params['sitype'] === 'url') {
            $txtParts = explode('|', $params['txt']);
            if (count($txtParts) < 2) {
                $response = "error code|error text|submission reference\n";
                $response .= "202|SI submission failed - Incorrect format. Must be: title|URL|0\n";
                return response($response, 200)
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }
            
            // Store original for userdefined
            if (empty($params['userdefined'])) {
                $params['userdefined'] = 'SERVICEINDICATOR=' . $params['txt'];
            } else {
                $params['userdefined'] .= '|~|SERVICEINDICATOR=' . $params['txt'];
            }
            
            // Convert to WAP Push binary format (simplified)
            // In production, this would use the pushGen function from the old system
            $title = $txtParts[0];
            $url = $txtParts[1];
            $params['txt'] = $this->generateWapPushBinary($title, $url);
        }
        
        Log::info('Legacy SI API Request', [
            'usr' => $params['usr'],
            'sitype' => $params['sitype'],
            'ip' => $request->ip()
        ]);
        
        $result = $this->smsSendingService->sendSms($params);
        
        return response($result['response'], 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
    
    /**
     * JSON response format (alternative to legacy format)
     */
    public function sendSmsJson(Request $request)
    {
        $params = [
            'usr' => $request->input('usr', ''),
            'pwd' => $request->input('pwd', ''),
            'userbigid' => $request->input('userbigid', ''),
            'itaggsecretkey' => $request->input('itaggsecretkey', ''),
            'from' => $request->input('from', ''),
            'to' => $request->input('to', ''),
            'txt' => $request->input('txt', ''),
            'type' => $request->input('type', 'text'),
            'route' => $request->input('route', 'd'),
            'send' => $request->input('send', ''),
            'dreceipt_url' => $request->input('dreceipt_url', ''),
            'userdefined' => $request->input('userdefined', ''),
            'campaignref' => $request->input('campaignref', ''),
            'initiator' => 'API',
        ];
        
        $result = $this->smsSendingService->sendSms($params);
        
        return response()->json([
            'success' => $result['success'],
            'error_code' => $result['error_code'],
            'error_text' => $result['error_text'],
            'submission_ref' => $result['submission_ref'],
            'bigid' => $result['bigid'] ?? null,
            'queued' => $result['queued'] ?? 0,
            'failed' => $result['failed'] ?? 0,
            'total' => $result['total'] ?? 0,
        ]);
    }
    
    /**
     * Check API credentials without sending
     */
    public function checkCredentials(Request $request)
    {
        $usr = $request->input('usr', '');
        $pwd = $request->input('pwd', '');
        
        if (empty($usr) || empty($pwd)) {
            return response()->json([
                'success' => false,
                'error' => 'Missing credentials'
            ], 400);
        }
        
        $user = \DB::table('users')
            ->where('uname', $usr)
            ->where('pword', $pwd)
            ->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid credentials'
            ], 401);
        }
        
        // Get wallet balance
        $balance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;
        
        return response()->json([
            'success' => true,
            'username' => $user->uname,
            'wallet_balance' => round($balance, 4),
            'bulk_throughput' => $user->bulk_throughput,
            'account_active' => $user->userpause !== 'y'
        ]);
    }
    
    /**
     * Get delivery status for a submission reference
     */
    public function getDeliveryStatus(Request $request)
    {
        $usr = $request->input('usr', '');
        $pwd = $request->input('pwd', '');
        $submissionRef = $request->input('submission_ref', '');
        
        if (empty($usr) || empty($pwd) || empty($submissionRef)) {
            return response()->json([
                'success' => false,
                'error' => 'Missing required parameters'
            ], 400);
        }
        
        // Verify credentials
        $user = \DB::table('users')
            ->where('uname', $usr)
            ->where('pword', $pwd)
            ->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid credentials'
            ], 401);
        }
        
        // Extract bigid from submission reference
        $bigid = explode('-', $submissionRef)[0];
        
        // Get messages for this bigid
        $messages = \DB::table('smsg_log')
            ->where('bigid', $bigid)
            ->where('userref', $user->bigid)
            ->select('mobnum', 'sentstatus', 'sentstatustext', 'deliverystatus1', 'deliverystatus2', 'timesent', 'userprice')
            ->get();
        
        if ($messages->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No messages found for this submission reference'
            ], 404);
        }
        
        $results = [];
        foreach ($messages as $msg) {
            $results[] = [
                'mobile' => $msg->mobnum,
                'status' => $msg->sentstatus,
                'status_text' => $msg->sentstatustext,
                'delivery_status' => $msg->deliverystatus2 ?: $msg->deliverystatus1,
                'sent_time' => $msg->timesent,
                'cost' => $msg->userprice
            ];
        }
        
        return response()->json([
            'success' => true,
            'submission_ref' => $submissionRef,
            'bigid' => $bigid,
            'message_count' => count($results),
            'messages' => $results
        ]);
    }
    
    /**
     * Generate WAP Push binary data (simplified)
     */
    protected function generateWapPushBinary(string $title, string $url): string
    {
        // This is a simplified version - the full implementation would use
        // the pushGen function from the legacy sisend.inc file
        // For now, return a placeholder that indicates binary content
        return ':06:05:04:0B:84:23:F0-' . bin2hex($title . '|' . $url);
    }
}
