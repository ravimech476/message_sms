<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SentSmsController extends Controller
{
    /**
     * Get initial page data (filter options)
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            // Source filter options
            $sourceOptions = [
                ['value' => 'itagg', 'label' => 'SMS Expert System'],
                ['value' => 'controlpanel', 'label' => 'Control Panel'],
                ['value' => 'api', 'label' => 'SMS APIs'],
                ['value' => 'email', 'label' => 'Email XML→SMS Gateway'],
                ['value' => 'mobyclip', 'label' => 'Mobyclip'],
            ];
            
            // Routes filter options
            $routeOptions = [
                ['value' => 'all', 'label' => 'All Routes'],
                ['value' => 'standard', 'label' => 'Standard Routes'],
                ['value' => 'premium', 'label' => 'Premium Routes'],
            ];
            
            // Delivery status filter options
            $deliveryOptions = [
                ['value' => 'all', 'label' => 'All Messages'],
                ['value' => 'delivered', 'label' => 'Delivered'],
                ['value' => 'failed', 'label' => 'Failed'],
                ['value' => 'pending', 'label' => 'Pending'],
                ['value' => 'scheduled', 'label' => 'Scheduled'],
            ];
            
            return response()->json([
                'success' => true,
                'data' => [
                    'source_options' => $sourceOptions,
                    'route_options' => $routeOptions,
                    'delivery_options' => $deliveryOptions,
                    'default_start_date' => Carbon::now('Europe/London')->startOfDay()->format('Y-m-d'),
                    'default_end_date' => Carbon::now('Europe/London')->format('Y-m-d'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sent SMS page data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load page data'
            ], 500);
        }
    }

    /**
     * Get sent messages with filters and pagination
     */
    public function messages(Request $request)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;
            
            // Get filter parameters
            $sources = $request->input('sources', ['itagg', 'controlpanel', 'api', 'email', 'mobyclip']);
            $route = $request->input('route', 'all');
            $deliveryStatus = $request->input('delivery_status', 'all');
            $mobile = $request->input('mobile', '');
            $startDate = $request->input('start_date', Carbon::now('Europe/London')->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now('Europe/London')->format('Y-m-d'));
            $startHour = $request->input('start_hour', '00');
            $startMinute = $request->input('start_minute', '00');
            $endHour = $request->input('end_hour', '23');
            $endMinute = $request->input('end_minute', '59');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 20);
            
            // Build conditions
            $conditions = [];
            
            // Route filter
            if ($route === 'standard') {
                $conditions[] = " AND requested_route < 10000";
            } elseif ($route === 'premium') {
                $conditions[] = " AND requested_route >= 10000";
            }
            
            // Delivery status filter
            // OLD SYSTEM uses capital letters: 'Delivered', 'Non Delivered', 'Lost Notification'
            // Also check lowercase for backward compatibility
            if ($deliveryStatus === 'delivered') {
                $conditions[] = " AND ((deliverystatus2 IN ('Delivered', 'delivered') AND requested_route<10000) OR (sentstatus='ok' AND requested_route>=10000))";
            } elseif ($deliveryStatus === 'failed') {
                $conditions[] = " AND (sentstatus IN ('fail') OR deliverystatus2 IN ('Non Delivered', 'non delivered', 'Failed', 'failed'))";
            } elseif ($deliveryStatus === 'pending') {
                $conditions[] = " AND ((sentstatus NOT IN ('fail') AND (deliverystatus2='' OR deliverystatus2 LIKE '%buffered%' OR deliverystatus2 IN ('pending', 'acked')) AND requested_route<10000))";
            } elseif ($deliveryStatus === 'scheduled') {
                $conditions[] = " AND sentstatus = 'tomorrowonward'";
            }
            
            // Date range filter
            if ($startDate && $endDate) {
                $startDateTime = str_replace("-", "", $startDate) . $startHour . $startMinute . '00';
                $endDateTime = str_replace("-", "", $endDate) . $endHour . $endMinute . '00';
                $conditions[] = " AND dosendtime BETWEEN '$startDateTime' AND '$endDateTime'";
            }
            
            // Mobile number filter
            if ($mobile) {
                $conditions[] = " AND mobnum = '" . $mobile . "'";
            }
            
            // Build the union query across all smsg_log tables
            $unionQuery = $this->getConsolidatedRawQuery($userBigId, $conditions);
            
            // Get total count
            $totalCountQuery = "SELECT COUNT(*) as total FROM ({$unionQuery}) as combined_logs";
            $totalRecords = DB::select($totalCountQuery)[0]->total;
            
            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $totalPages = ceil($totalRecords / $perPage);
            
            // Get paginated records
            $paginatedQuery = "$unionQuery ORDER BY dosendtime DESC LIMIT $perPage OFFSET $offset";
            $records = DB::select($paginatedQuery);
            
            // Format the records
            $messages = [];
            foreach ($records as $record) {
                $status = $this->mapDeliveryStatus($record->deliverystatus2 ?? $record->sentstatus ?? 'unknown');
                
                $messages[] = [
                    'id' => $record->id,
                    'table_name' => $record->table_name,
                    'mobile' => $record->mobnum ?? 'Unknown',
                    // Stored URL-encoded + Windows-1252 (£ = %A3) for OLD parity — decode for UTF-8/JSON.
                    'message_preview' => mb_substr(decodeSmsTextForDisplay($record->text ?? ''), 0, 50) . (mb_strlen(decodeSmsTextForDisplay($record->text ?? '')) > 50 ? '...' : ''),
                    'message_full' => decodeSmsTextForDisplay($record->text ?? ''),
                    // "Sent" time MUST come from timesent (actual send time, stored in UK/Europe-London).
                    // deliverytime2 is the DELIVERY-RECEIPT time and is stored in GMT — using it here made
                    // the Sent column read 1 hour behind UK time during BST.
                    'sent_time' => $this->formatSentTime($record->timesent),
                    'sent_time_raw' => $record->timesent,
                    'status' => $status['label'],
                    'status_code' => $status['code'],
                    'originator' => decodeSmsTextForDisplay($record->originator ?? ''),
                    'initiator' => $record->initiator ?? 'System',
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => $messages,
                    'pagination' => [
                        'current_page' => (int)$page,
                        'per_page' => (int)$perPage,
                        'total_pages' => $totalPages,
                        'total_records' => $totalRecords,
                        'has_more' => $page < $totalPages,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sent messages: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load messages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get message details
     */
    public function show(Request $request, $tableName, $id)
    {
        try {
            $user = $request->user();
            
            // Validate table name to prevent SQL injection
            if (!preg_match('/^smsg_log/', $tableName)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid table name'
                ], 400);
            }
            
            // Get delivery date for summer time check
            $sqldate = "SELECT DATE_FORMAT(CONCAT(deliverytime2, '00'), '%Y%m%d') AS deliverytime2 FROM $tableName WHERE id = ?";
            $delivered = DB::select($sqldate, [$id]);
            
            if (!$delivered) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }
            
            $deliveredDate = $delivered[0]->deliverytime2;
            $isSummer = $this->isSummerTime($deliveredDate);
            
            // OLD SYSTEM parity: deliverytime2 is stored in GMT/UTC. Add 1 HOUR during BST to
            // show UK local; no offset in GMT (winter).
            if ($isSummer) {  // BST: UTC + 1 hour
                $sql = "SELECT DATE_FORMAT(timesubmitted,'%W, %D %M %Y %T') as timesubmitted,
                        text, mobnum, originator, initiator, requested_route, userprice, SItype, numparts,
                        CASE WHEN (deliverytime2 IS NULL OR deliverytime2 = '') THEN NULL
                             ELSE DATE_FORMAT(dosendtime + INTERVAL 1 SECOND, '%W, %D %M %Y %T') END as deliverytime,
                        upstream_errormessage, userdefined, countrydialcode,
                        DATE_FORMAT(dosendtime, '%W, %D %M %Y %T') as dosendtime,
                        sentstatus, sentstatustext, deliverystatus1, deliverystatus2,
                        DATE_FORMAT(timesent, '%W, %D %M %Y %T') as timesent
                FROM $tableName WHERE id = ?";
            } else {
                $sql = "SELECT DATE_FORMAT(timesubmitted,'%W, %D %M %Y %T') as timesubmitted,
                        text, mobnum, originator, initiator, requested_route, userprice, SItype, numparts,
                        CASE WHEN (deliverytime2 IS NULL OR deliverytime2 = '') THEN NULL
                             ELSE DATE_FORMAT(dosendtime + INTERVAL 1 SECOND, '%W, %D %M %Y %T') END as deliverytime,
                        upstream_errormessage, userdefined, countrydialcode,
                        DATE_FORMAT(dosendtime, '%W, %D %M %Y %T') as dosendtime,
                        sentstatus, sentstatustext, deliverystatus1, deliverystatus2,
                        DATE_FORMAT(timesent, '%W, %D %M %Y %T') as timesent
                FROM $tableName WHERE id = ?";
            }
            
            $result = DB::select($sql, [$id]);
            
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }
            
            $row = $result[0];
            
            // Get cost (userprice is already the total, already multiplied by numparts when stored)
            $userPrice = (float)($row->userprice ?? 0);
            
            // Map delivery status
            $statusMap = [
                'pending' => 'Pending',
                'tomorrowonward' => 'Scheduled',
                'accepted' => 'Accepted',
                'delivered' => 'Delivered',
                'Delivered' => 'Delivered',
                'buffered' => 'Buffered',
                'expired' => 'Expired',
                'failed' => 'Failed',
                'fail' => 'Failed',
                'rejected' => 'Rejected',
                'ok' => 'Completed',
                'unknown' => 'Unknown'
            ];
            
            $rawStatus = $row->deliverystatus2 ?? $row->sentstatus ?? 'unknown';
            $mappedStatus = $statusMap[strtolower($rawStatus)] ?? ucfirst($rawStatus);
            
            $details = [
                'id' => $id,
                'table_name' => $tableName,
                'date_submitted' => $row->timesubmitted ?? 'N/A',
                'send_at_time' => $row->dosendtime ?? 'N/A',
                'sent_at_time' => $row->timesent ?? 'N/A',
                'delivery_time' => $row->deliverytime ?? 'N/A',
                // Stored URL-encoded + Windows-1252 (£ = %A3) for OLD parity — decode for UTF-8/JSON.
                'sender' => decodeSmsTextForDisplay($row->originator ?? 'N/A'),
                'message' => decodeSmsTextForDisplay($row->text ?? ''),
                'sent_to' => $row->mobnum ?? 'N/A',
                'sent_by' => $this->sanitiseString($row->initiator ?? 'System'),
                'recipient_cost' => '£' . number_format($userPrice, 2),
                'cost_to_you' => '£' . number_format($userPrice, 2),
                'delivery_status' => $mappedStatus,
                'message_status' => $row->sentstatustext ?? 'N/A',
                'num_parts' => $row->numparts ?? 1,
                'country_code' => $row->countrydialcode ?? '',
                'requested_route' => $row->requested_route ?? 0,
            ];
            
            return response()->json([
                'success' => true,
                'data' => $details
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching message details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load message details'
            ], 500);
        }
    }

    /**
     * Get sent SMS statistics
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            $userBigId = $user->bigid;
            
            $today = Carbon::now('Europe/London')->format('Ymd');
            $weekStart = Carbon::now('Europe/London')->startOfWeek()->format('Ymd');
            $monthStart = Carbon::now('Europe/London')->startOfMonth()->format('Ymd');
            
            // Build union query for all smsg_log tables
            $unionQuery = $this->getConsolidatedRawQuery($userBigId, []);
            
            // Get stats
            $statsQuery = "SELECT 
                COUNT(*) as total_messages,
                SUM(CASE WHEN LEFT(dosendtime, 8) = '$today' THEN 1 ELSE 0 END) as today_messages,
                SUM(CASE WHEN LEFT(dosendtime, 8) >= '$weekStart' THEN 1 ELSE 0 END) as week_messages,
                SUM(CASE WHEN LEFT(dosendtime, 8) >= '$monthStart' THEN 1 ELSE 0 END) as month_messages,
                SUM(CASE WHEN deliverystatus2 = 'Delivered' OR sentstatus = 'ok' THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN sentstatus = 'fail' OR deliverystatus2 IN ('Failed', 'Non Delivered') THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN sentstatus = 'pending' OR deliverystatus2 = 'pending' THEN 1 ELSE 0 END) as pending_count
                FROM ({$unionQuery}) as combined_logs";
            
            $stats = DB::select($statsQuery);
            
            if ($stats && count($stats) > 0) {
                $stat = $stats[0];
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_messages' => (int)($stat->total_messages ?? 0),
                        'today_messages' => (int)($stat->today_messages ?? 0),
                        'week_messages' => (int)($stat->week_messages ?? 0),
                        'month_messages' => (int)($stat->month_messages ?? 0),
                        'delivered_count' => (int)($stat->delivered_count ?? 0),
                        'failed_count' => (int)($stat->failed_count ?? 0),
                        'pending_count' => (int)($stat->pending_count ?? 0),
                    ]
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_messages' => 0,
                    'today_messages' => 0,
                    'week_messages' => 0,
                    'month_messages' => 0,
                    'delivered_count' => 0,
                    'failed_count' => 0,
                    'pending_count' => 0,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sent SMS stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics'
            ], 500);
        }
    }

    /**
     * Build consolidated query across all smsg_log tables
     */
    private function getConsolidatedRawQuery($userBigId, array $conditions)
    {
        $tables = DB::select("
            SELECT table_name
            FROM INFORMATION_SCHEMA.TABLES
            WHERE table_name LIKE 'smsg_log%'
            AND TABLE_SCHEMA = DATABASE()
        ");
        
        $queries = collect($tables)->map(function ($table) use ($userBigId, $conditions) {
            $tableName = $table->table_name ?? $table->TABLE_NAME;
            $query = "SELECT *, '{$tableName}' AS table_name FROM {$tableName} WHERE userref='" . $userBigId . "'";
            foreach ($conditions as $condition) {
                $query .= " $condition";
            }
            return $query;
        });
        
        return implode(' UNION ALL ', $queries->toArray());
    }

    /**
     * Format sent time for display
     */
    private function formatSentTime($timeStr)
    {
        if (!$timeStr || $timeStr === '00000000000000') {
            return 'In progress';
        }
        
        try {
            // Handle datetime format (YYYY-MM-DD HH:MM:SS)
            if (strpos($timeStr, '-') !== false) {
                return Carbon::parse($timeStr)->format('D jS M Y H:i');
            }
            
            // Handle YmdHis format
            if (strlen($timeStr) >= 14) {
                $year = substr($timeStr, 0, 4);
                $month = substr($timeStr, 4, 2);
                $day = substr($timeStr, 6, 2);
                $hour = substr($timeStr, 8, 2);
                $min = substr($timeStr, 10, 2);
                
                return Carbon::create($year, $month, $day, $hour, $min)->format('D jS M Y H:i');
            }
            
            return $timeStr;
        } catch (\Exception $e) {
            return $timeStr;
        }
    }

    /**
     * Map delivery status to label and code
     */
    private function mapDeliveryStatus($status)
    {
        $statusMap = [
            'pending' => ['label' => 'Pending', 'code' => 'pending'],
            'delivered' => ['label' => 'Delivered', 'code' => 'delivered'],
            'Delivered' => ['label' => 'Delivered', 'code' => 'delivered'],
            'failed' => ['label' => 'Failed', 'code' => 'failed'],
            'fail' => ['label' => 'Failed', 'code' => 'failed'],
            'rejected' => ['label' => 'Rejected', 'code' => 'failed'],
            'Non Delivered' => ['label' => 'Failed', 'code' => 'failed'],
            'tomorrowonward' => ['label' => 'Scheduled', 'code' => 'scheduled'],
            'ok' => ['label' => 'Completed', 'code' => 'delivered'],
            'buffered' => ['label' => 'Buffered', 'code' => 'pending'],
            'expired' => ['label' => 'Expired', 'code' => 'failed'],
        ];
        
        $lowerStatus = strtolower($status);
        
        if (isset($statusMap[$status])) {
            return $statusMap[$status];
        }
        
        if (isset($statusMap[$lowerStatus])) {
            return $statusMap[$lowerStatus];
        }
        
        return ['label' => ucfirst($status), 'code' => 'unknown'];
    }

    /**
     * Check if date is in summer time (BST).
     *
     * EXACT COPY of the OLD SYSTEM function (britishsummertime.inc) for OLD-SYSTEM parity.
     * OLD only defines BST for 2004-2006 and returns FALSE for every other date, so modern
     * deliveries always use the GMT branch (no +1 hour). Intentional — see helpers.php::isSummerTime.
     */
    private function isSummerTime($dateStr)
    {
        $arrBst = array('20040328' => '20041031', '20050327' => '20051030', '20060326' => '20061029');
        foreach ($arrBst as $start => $stop) {
            if (($dateStr >= $start) && ($dateStr < $stop)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitise string for display
     */
    private function sanitiseString($str)
    {
        if (!$str) return '';
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}
