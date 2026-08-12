<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\User;

/**
 * Mobile App SMS Controller
 * 
 * Handles SMS sending and history for the mobile application
 */
class SmsController extends Controller
{
    /**
     * Send SMS
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @bodyParam to string required Recipient phone number. Example: 447912345678
     * @bodyParam message string required SMS message content. Example: Hello World
     * @bodyParam from string optional Sender ID. Example: MyCompany
     * @bodyParam schedule_at string optional Schedule time (ISO 8601). Example: 2024-12-10T10:00:00Z
     */
    public function send(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'to' => 'required|string|min:10|max:15',
                'message' => 'required|string|min:1|max:1600',
                'from' => 'nullable|string|max:11',
                'schedule_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $bigid = $user->bigid;

            // Check wallet balance
            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;
            
            // Get user price per SMS (you may need to adjust this based on your pricing logic)
            $smsPrice = $this->getSmsPrice($user, $request->to);
            
            if ($walletBalance < $smsPrice) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient balance',
                    'errors' => [
                        'wallet' => ['Your wallet balance is insufficient to send this SMS.']
                    ],
                    'data' => [
                        'balance' => round($walletBalance, 2),
                        'required' => round($smsPrice, 2),
                    ]
                ], 402);
            }

            // Prepare SMS data
            $recipient = $this->formatPhoneNumber($request->to);
            $message = $request->message;
            $from = $request->from ?? $user->defaultfrom ?? 'SMS Expert';
            $timestamp = Carbon::now('Europe/London')->format('YmdHis');

            // Calculate message parts
            $messageParts = $this->calculateMessageParts($message);

            // Calculate dosendtimeint as Unix timestamp (like old system)
            $dosendtimeint = mktime(
                (int) substr($timestamp, 8, 2),
                (int) substr($timestamp, 10, 2),
                (int) substr($timestamp, 12, 2),
                (int) substr($timestamp, 4, 2),
                (int) substr($timestamp, 6, 2),
                (int) substr($timestamp, 0, 4)
            );

            // Daemon priority
            $baseDaemonId = 100;
            $daemonId = $baseDaemonId + mt_rand(0, 39);

            // Insert SMS into queue/log
            $bigid_unique = md5(uniqid(rand(), true));
            $smsId = DB::table('smsg_log')->insertGetId([
                'sms_type' => 'sms',
                'initiator' => 'MobileApp',
                'bigid' => $bigid_unique,
                'mobnum' => $recipient,
                'text' => $message,
                'originator' => $from,
                'numbits' => 7,
                'timesubmitted' => $timestamp,
                'userref' => $bigid,
                'affiliateref' => '0',
                'dosendtime' => $timestamp,
                'dosendtimeint' => $dosendtimeint,
                'dayofyear' => substr($timestamp, 0, 8),
                'timesent' => $timestamp,
                'sentstatus' => 'ok',
                'sentstatustmp' => 'ok',
                'sentstatustext' => 'SMS Queued',
                'suppliermsgref' => '',
                'smsgdaemonid' => $daemonId,
                'sendpriority' => $baseDaemonId,
                'numparts' => $messageParts,
                'costprice' => 0.000000,
                'userprice' => $smsPrice,
                'aggregator_dlrcode' => 0,
                'aggregator_dlrmsg' => 'Queued',
                'campaignref' => '',
                'binaryflags' => '',
                'profit' => 0.000000,
                'countrydialcode' => '',
                'suppliername' => '',
                'supplierrouteref' => '',
                'requested_route' => 0,
                'requested_routetag' => '',
                'deliverystatus2' => 'pending',
                'migration_flag' => 'new',
            ]);

            // Update user's sent counter (you may want to handle this differently)
            // User::where('bigid', $bigid)->increment('smsg_server1_sent', $smsPrice);

            return response()->json([
                'status' => true,
                'message' => 'SMS sent successfully',
                'data' => [
                    'sms_id' => $smsId,
                    'to' => $recipient,
                    'from' => $from,
                    'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
                    'parts' => $messageParts,
                    'cost' => round($smsPrice, 4),
                    'sent_at' => Carbon::now()->toIso8601String(),
                    'status' => 'PENDING',
                ],
            ], 201);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Send SMS Error: ' . $ex->getMessage(), [
                'user_id' => $request->user()->id ?? null,
                'to' => $request->to ?? null,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send SMS',
                'errors' => [
                    'server' => ['Unable to send SMS. Please try again later.']
                ]
            ], 500);
        }
    }

    /**
     * Get SMS history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Pagination
            $page = (int) $request->get('page', 1);
            $perPage = min((int) $request->get('per_page', 20), 100);
            $offset = ($page - 1) * $perPage;

            // Filters
            $status = $request->get('status');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $search = $request->get('search');

            // Build query
            $query = DB::table('smsg_log')
                ->where('userref', $bigid)
                ->where('sentstatus', 'ok');

            // Apply filters
            if ($status) {
                $statusLower = strtolower($status);
                if ($statusLower === 'delivered') {
                    $query->whereRaw("LOWER(deliverystatus2) = 'delivered'");
                } elseif ($statusLower === 'failed') {
                    $query->whereRaw("LOWER(deliverystatus2) IN ('failed', 'undelivered', 'rejected', 'expired')");
                } elseif ($statusLower === 'pending') {
                    $query->where(function ($q) {
                        $q->whereNull('deliverystatus2')
                          ->orWhere('deliverystatus2', '')
                          ->orWhereRaw("LOWER(deliverystatus2) IN ('pending', 'buffered', 'accepted')");
                    });
                } else {
                    $query->whereRaw("LOWER(deliverystatus2) = ?", [$statusLower]);
                }
            }

            if ($dateFrom) {
                $dateFromFormatted = Carbon::parse($dateFrom)->format('YmdHis');
                $query->where('timesent', '>=', $dateFromFormatted);
            }

            if ($dateTo) {
                $dateToFormatted = Carbon::parse($dateTo)->endOfDay()->format('YmdHis');
                $query->where('timesent', '<=', $dateToFormatted);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('recipient', 'like', "%{$search}%")
                      ->orWhere('text', 'like', "%{$search}%")
                      ->orWhere('originator', 'like', "%{$search}%");
                });
            }

            // Get total count
            $total = $query->count();

            // Get paginated results
            $smsHistory = $query
                ->select('id', 'recipient', 'originator', 'text', 'deliverystatus2', 'timesent', 'numparts', 'userprice')
                ->orderBy('timesent', 'desc')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function ($sms) {
                    $status = $this->formatStatus($sms->deliverystatus2);
                    return [
                        'id' => $sms->id,
                        'to' => $sms->recipient,
                        'from' => $sms->originator,
                        'message' => $sms->text,
                        'message_preview' => substr($sms->text ?? '', 0, 80) . (strlen($sms->text ?? '') > 80 ? '...' : ''),
                        'status' => $status,
                        'status_label' => $this->getStatusLabel($status),
                        'parts' => (int) ($sms->numparts ?? 1),
                        'cost' => round($sms->userprice ?? 0, 4),
                        'sent_at' => $this->formatTimestamp($sms->timesent),
                    ];
                });

            $totalPages = ceil($total / $perPage);

            return response()->json([
                'status' => true,
                'message' => 'SMS history retrieved successfully',
                'data' => [
                    'items' => $smsHistory,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile SMS History Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load SMS history'
            ], 500);
        }
    }

    /**
     * Get single SMS details
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            $sms = DB::table('smsg_log')
                ->where('id', $id)
                ->where('userref', $bigid)
                ->first();

            if (!$sms) {
                return response()->json([
                    'status' => false,
                    'message' => 'SMS not found'
                ], 404);
            }

            $status = $this->formatStatus($sms->deliverystatus2 ?? null);

            return response()->json([
                'status' => true,
                'message' => 'SMS details retrieved successfully',
                'data' => [
                    'id' => $sms->id,
                    'to' => $sms->recipient,
                    'from' => $sms->originator,
                    'message' => $sms->text,
                    'status' => $status,
                    'status_label' => $this->getStatusLabel($status),
                    'parts' => (int) ($sms->numparts ?? 1),
                    'cost' => round($sms->userprice ?? 0, 4),
                    'cost_price' => round($sms->costprice ?? 0, 4),
                    'sent_at' => $this->formatTimestamp($sms->timesent),
                    'delivered_at' => $this->formatDeliveryTimestamp($sms->dosendtime ?? $sms->timesent ?? null, $sms->deliverytime2 ?? null),
                    'delivery_status' => $sms->deliverystatus2 ?? null,
                    'network' => $sms->network ?? null,
                    'route' => $sms->route ?? null,
                    'source' => $sms->source ?? 'web',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile SMS Show Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load SMS details'
            ], 500);
        }
    }

    /**
     * Get SMS price for user
     */
    private function getSmsPrice($user, string $recipient): float
    {
        // Default price - you should implement your pricing logic here
        $defaultPrice = 0.035;

        try {
            // Check user-specific pricing
            $userPrice = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->value('smsprice');

            if ($userPrice) {
                return (float) $userPrice;
            }

            // Check destination-based pricing
            // You can implement this based on your routing/pricing tables

        } catch (\Exception $e) {
            \Log::warning('Failed to get SMS price: ' . $e->getMessage());
        }

        return $defaultPrice;
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing (default UK)
        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            $phone = '44' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Calculate message parts
     */
    private function calculateMessageParts(string $message): int
    {
        $length = strlen($message);
        
        // Check if message contains non-GSM characters (Unicode)
        $isUnicode = preg_match('/[^\x00-\x7F]/', $message);

        if ($isUnicode) {
            // Unicode: 70 chars single, 67 chars per part multipart
            return $length <= 70 ? 1 : (int) ceil($length / 67);
        } else {
            // GSM: 160 chars single, 153 chars per part multipart
            return $length <= 160 ? 1 : (int) ceil($length / 153);
        }
    }

    /**
     * Format timestamp
     */
    private function formatTimestamp(?string $timestamp): ?string
    {
        if (!$timestamp) return null;

        try {
            return Carbon::createFromFormat('YmdHis', $timestamp)->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * "Date Finalised" for a delivered message = Send-at-Time + 1 second.
     *
     * The carrier's SMPP done_date is unreliable (Vonage returns it in the destination
     * carrier's timezone — e.g. India = IST — with no tz marker), so we do NOT derive the
     * finalised time from it. Instead it's simply the send time + 1 second, shown only once
     * the message is finalised (deliverytime2 populated).
     *
     * @param string|null $sendTime      dosendtime/timesent, 14-digit YYYYMMDDHHMMSS (UK-local)
     * @param string|null $deliverytime2 gate: only finalised rows show a value
     */
    private function formatDeliveryTimestamp(?string $sendTime, ?string $deliverytime2 = null): ?string
    {
        if (empty($deliverytime2) || empty($sendTime)) return null;

        $digits = preg_replace('/\D/', '', $sendTime);
        if (strlen($digits) !== 14) return null;

        try {
            return Carbon::createFromFormat('YmdHis', $digits, 'Europe/London')
                ->addSecond()
                ->toIso8601String();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format status for display
     */
    private function formatStatus(?string $status): string
    {
        if (!$status || $status === '') return 'PENDING';
        
        $status = strtolower($status);
        
        return match($status) {
            'delivered' => 'DELIVERED',
            'failed', 'undelivered', 'rejected' => 'FAILED',
            'expired' => 'EXPIRED',
            'pending', 'buffered', 'accepted' => 'PENDING',
            default => strtoupper($status),
        };
    }

    /**
     * Get human-readable status label
     */
    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'DELIVERED' => 'Delivered',
            'FAILED' => 'Failed',
            'EXPIRED' => 'Expired',
            'PENDING' => 'Pending',
            'REJECTED' => 'Rejected',
            default => ucfirst(strtolower($status)),
        };
    }
}
