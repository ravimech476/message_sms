<?php

namespace App\Http\Controllers;

use App\Services\Queue\SmsQueueService;
use App\Services\Queue\RabbitMQService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SmppSmsController extends Controller
{
    private $smsQueueService;
    private $rabbitMQ;
    
    public function __construct()
    {
        $this->smsQueueService = new SmsQueueService();
        $this->rabbitMQ = new RabbitMQService();
    }
    
    /**
     * Send SMS via SMPP Queue
     */
    public function sendSms(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required|string',
            'message' => 'required|string|max:1000',
            'sender_id' => 'nullable|string|max:11',
            'priority' => 'nullable|integer|min:1|max:10',
            'scheduled_at' => 'nullable|date',
        ]);
        
        // Get user info
        $userInfo = Session::get('user_info');
        $userRef = $userInfo['bigid'] ?? 'api_user';
        
        // Process multiple numbers
        $numbers = explode(',', $validated['to']);
        $results = [];
        
        foreach ($numbers as $number) {
            $number = trim($number);
            
            // Queue the SMS
            $result = $this->smsQueueService->queueSms([
                'user_ref' => $userRef,
                'mobile_number' => $number,
                'message' => $validated['message'],
                'sender_id' => $validated['sender_id'] ?? null,
                'priority' => $validated['priority'] ?? 5,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'metadata' => [
                    'source' => 'web',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);
            
            $results[] = $result;
            
            // Also store in smsg_log for compatibility
            if ($result['success']) {
                $this->storeInSmsgLog($userRef, $number, $validated['message'], $validated['sender_id']);
            }
        }
        
        // Check if all successful
        $allSuccess = collect($results)->every(function ($result) {
            return $result['success'];
        });
        
        if ($allSuccess) {
            return response()->json([
                'success' => true,
                'message' => 'SMS queued successfully',
                'queue_ids' => collect($results)->pluck('queue_id')->toArray()
            ]);
        } else {
            $errors = collect($results)->where('success', false)->pluck('error')->toArray();
            return response()->json([
                'success' => false,
                'message' => 'Some messages failed to queue',
                'errors' => $errors
            ], 400);
        }
    }
    
    /**
     * Get queue statistics
     */
    public function queueStatus(Request $request)
    {
        try {
            $stats = $this->smsQueueService->getStatistics();
            
            return response()->json([
                'success' => true,
                'statistics' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get queue depth for all queues
     */
    public function queueDepth(Request $request)
    {
        try {
            $queues = [
                'sms.outbound' => $this->rabbitMQ->getQueueStats('sms.outbound'),
                'sms.priority' => $this->rabbitMQ->getQueueStats('sms.priority'),
                'sms.dlr' => $this->rabbitMQ->getQueueStats('sms.dlr'),
                'sms.failed' => $this->rabbitMQ->getQueueStats('sms.failed')
            ];
            
            return response()->json([
                'success' => true,
                'queues' => $queues
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get SMS status by bigid (queue ID format)
     * NOTE: sms_queue table removed - using smsg_log instead
     */
    public function getSmsStatus($queueId)
    {
        try {
            // Try to find in smsg_log by bigid
            $sms = DB::table('smsg_log')->where('bigid', $queueId)->first();

            // If not found by bigid, try to find by suppliermsgref (message_id)
            if (!$sms) {
                $sms = DB::table('smsg_log')->where('suppliermsgref', $queueId)->first();
            }

            if (!$sms) {
                return response()->json([
                    'success' => false,
                    'error' => 'SMS not found'
                ], 404);
            }

            // Get DLR if available
            $dlr = null;
            if ($sms->suppliermsgref) {
                $dlr = DB::table('sms_dlr')
                    ->where('message_id', $sms->suppliermsgref)
                    ->first();
            }

            return response()->json([
                'success' => true,
                'sms' => [
                    'queue_id' => $sms->bigid,
                    'status' => $sms->sentstatus,
                    'mobile_number' => $sms->mobnum,
                    'message' => $sms->text,
                    'sender_id' => $sms->originator,
                    'created_at' => $sms->timesubmitted,
                    'sent_at' => $sms->timesent,
                    'message_id' => $sms->suppliermsgref,
                    'delivery_status' => $sms->deliverystatus2,
                    'error_message' => $sms->sentstatustext
                ],
                'dlr' => $dlr ? [
                    'status' => $dlr->status,
                    'status_text' => $dlr->status_text,
                    'error_code' => $dlr->error_code,
                    'done_date' => $dlr->done_date,
                    'network_code' => $dlr->network_code
                ] : null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Purge failed queue
     * NOTE: sms_queue table removed - only purges RabbitMQ queue now
     */
    public function purgeFailedQueue(Request $request)
    {
        try {
            $this->rabbitMQ->purgeQueue('sms.failed');

            return response()->json([
                'success' => true,
                'message' => 'Failed queue purged successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed messages from smsg_log
     * NOTE: sms_queue table removed - using smsg_log instead
     */
    public function retryFailed(Request $request)
    {
        try {
            $limit = $request->input('limit', 100);

            // Get failed messages from smsg_log
            $failedMessages = DB::table('smsg_log')
                ->where('sentstatus', 'fail')
                ->limit($limit)
                ->get();

            $retried = 0;
            foreach ($failedMessages as $sms) {
                // Reset status and resend via SMPP service
                DB::table('smsg_log')
                    ->where('id', $sms->id)
                    ->update([
                        'sentstatus' => 'pending',
                        'sentstatustext' => 'Retrying'
                    ]);

                // Queue for retry (will send directly via SMPP)
                $this->smsQueueService->queueSms([
                    'user_ref' => $sms->userref,
                    'mobile_number' => $sms->mobnum,
                    'message' => $sms->text,
                    'sender_id' => $sms->originator,
                    'priority' => 5,
                    'reference_id' => $sms->bigid
                ]);

                $retried++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "Retried {$retried} failed messages"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store in smsg_log for backward compatibility
     */
    private function storeInSmsgLog($userRef, $mobile, $message, $senderId = null)
    {
        try {
            $bigid = md5(uniqid(rand(), true));
            $from = $senderId ?: 'MYBRANDNAME';
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
                'bigid' => $bigid,
                'mobnum' => $mobile,
                'text' => $message,
                'originator' => $from,
                'numbits' => 7,
                'timesubmitted' => $datenow,
                'userref' => $userRef,
                'affiliateref' => '0',
                'dosendtime' => $datenow,
                'dosendtimeint' => $dosendtimeint,
                'dayofyear' => substr($datenow, 0, 8),
                'timesent' => '00000000000000',
                'sentstatus' => 'pending',
                'sentstatustmp' => 'pending',
                'sentstatustext' => 'Queued for SMPP delivery',
                'suppliermsgref' => '',
                'smsgdaemonid' => $daemonId,
                'sendpriority' => $baseDaemonId,
                'costprice' => 0.000000,
                'userprice' => 0.000000,
                'aggregator_dlrcode' => 0,
                'aggregator_dlrmsg' => 'Pending',
                'campaignref' => '',
                'binaryflags' => '',
                'profit' => 0.000000,
                'suppliername' => '',
                'supplierrouteref' => '',
                'requested_route' => 0,
                'requested_routetag' => '',
                'deliverystatus2' => 'pending',
                'migration_flag' => 'new',
                'created_at' => Carbon::now()
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to store in smsg_log: " . $e->getMessage());
        }
    }
}
