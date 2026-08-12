<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReceivedSmsController extends Controller
{
    /**
     * Get received SMS page initial data
     * GET /api/mobile/received-sms
     * 
     * Returns: keywords list for filter dropdown
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;
            $date = date('Y-m-d');

            // Get user's keywords/virtual numbers for filter dropdown
            $keywords = DB::table('itagg_instance')
                ->join('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                ->select('itagg_instance.id', 'keyword', 'number')
                ->where('users_bigid', $bigid)
                ->where('status', 1)
                ->where('expiry', '>=', $date)
                ->get()
                ->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'keyword' => $row->keyword,
                        'number' => $row->number,
                        'display_name' => $row->keyword . ' (' . $row->number . ')'
                    ];
                })
                ->toArray();

            // Add special filter options
            $filterOptions = [
                [
                    'id' => 'all',
                    'keyword' => 'All',
                    'number' => '',
                    'display_name' => 'All Incoming Messages'
                ],
                [
                    'id' => '-1',
                    'keyword' => 'STOPs',
                    'number' => '60300/80809',
                    'display_name' => 'STOPs (60300/80809)'
                ],
                [
                    'id' => '-2',
                    'keyword' => 'STOPs',
                    'number' => '447786201088',
                    'display_name' => 'STOPs (447786201088)'
                ],
                ...$keywords
            ];

            // Get total unread count
            $totalCount = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid)
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Received SMS data retrieved successfully',
                'data' => [
                    'filter_options' => $filterOptions,
                    'total_messages' => $totalCount,
                    'default_filter' => 'all'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching received SMS data', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load received SMS data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get received SMS messages with filters and pagination
     * GET /api/mobile/received-sms/messages
     * 
     * Query params:
     * - filter: 'all', '-1', '-2', or itagg_id
     * - start_date: Y-m-d format
     * - end_date: Y-m-d format
     * - start_time: HH:mm format
     * - end_time: HH:mm format
     * - page: pagination page number
     * - per_page: items per page (default 20, max 100)
     * - search: search in message content or sender
     */
    public function messages(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Get filter parameters
            $filter = $request->input('filter', 'all');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $startTime = $request->input('start_time', '00:00');
            $endTime = $request->input('end_time', '23:59');
            $page = max(1, (int)$request->input('page', 1));
            $perPage = min(100, max(1, (int)$request->input('per_page', 20)));
            $search = $request->input('search', '');

            // Build query
            $query = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid);

            // Apply filter based on selection
            if ($filter === 'all' || $filter === 'All Incoming') {
                // No additional filter for all incoming
            } elseif ($filter === '-1') {
                // STOP messages on 60300/80809
                $query->whereRaw("LOWER(msg) LIKE '%stop%'")
                    ->whereIn('dest', ['60300', '80809']);
            } elseif ($filter === '-2') {
                // STOP messages on dedicated number
                $query->whereRaw("LOWER(msg) LIKE '%stop%'")
                    ->where('dest', '447786201088');
            } elseif (is_numeric($filter)) {
                // Specific iTAGG instance
                $itaggInstance = DB::table('itagg_instance')
                    ->join('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                    ->where('itagg_instance.id', $filter)
                    ->first();

                if ($itaggInstance) {
                    if ($itaggInstance->keyword !== '*') {
                        $query->where('keyword', 'LIKE', $itaggInstance->keyword);
                    }
                    $query->where('dest', 'LIKE', $itaggInstance->number);

                    // Apply date range based on keyword ownership period
                    $start = date('Ymd', strtotime($itaggInstance->purchased)) . '000000';
                    $finish = date('Ymd', strtotime($itaggInstance->expiry)) . '235959';
                    $query->whereBetween('recieved', [$start, $finish]);
                }
            }

            // Apply date range filter if provided
            if ($startDate && $endDate) {
                $startDateTime = str_replace('-', '', $startDate) . str_replace(':', '', $startTime) . '00';
                $endDateTime = str_replace('-', '', $endDate) . str_replace(':', '', $endTime) . '59';
                $query->whereBetween('recieved', [$startDateTime, $endDateTime]);
            }

            // Apply search filter
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('source', 'LIKE', "%{$search}%")
                        ->orWhere('msg', 'LIKE', "%{$search}%");
                });
            }

            // Get total count for pagination
            $totalRecords = $query->count();
            $totalPages = ceil($totalRecords / $perPage);
            $offset = ($page - 1) * $perPage;

            // Get paginated results
            $messages = $query->select(
                    'id',
                    'source',
                    'msg',
                    'recieved',
                    'network',
                    'dest',
                    'keyword',
                    'msisdnAlias'
                )
                ->orderBy('recieved', 'DESC')
                ->offset($offset)
                ->limit($perPage)
                ->get()
                ->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'sender' => $message->source,
                        'message' => urldecode($message->msg),
                        'message_preview' => mb_substr(urldecode($message->msg), 0, 50) . (mb_strlen(urldecode($message->msg)) > 50 ? '...' : ''),
                        'received_at' => $this->formatReceivedDate($message->recieved),
                        'received_at_raw' => $message->recieved,
                        'received_to' => $message->dest,
                        'keyword' => $message->keyword,
                        'network' => $message->network,
                        'msisdn_alias' => $message->msisdnAlias
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Messages retrieved successfully',
                'data' => [
                    'messages' => $messages,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_pages' => $totalPages,
                        'total_records' => $totalRecords,
                        'has_more' => $page < $totalPages
                    ],
                    'filters' => [
                        'filter' => $filter,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'search' => $search
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching received SMS messages', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single message details
     * GET /api/mobile/received-sms/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Get message details
            $message = DB::table('itagg_incominglog')
                ->where('id', $id)
                ->where('user_bigid', $bigid)
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Message not found'
                ], 404);
            }

            // Check if there was an auto-response sent
            $autoResponse = $this->getAutoResponse($message);

            return response()->json([
                'success' => true,
                'message' => 'Message details retrieved successfully',
                'data' => [
                    'id' => $message->id,
                    'sender' => $message->source,
                    'message' => urldecode($message->msg),
                    'received_at' => $this->formatReceivedDate($message->recieved),
                    'received_at_formatted' => $this->formatReceivedDateLong($message->recieved),
                    'received_to' => $message->dest,
                    'keyword' => $message->keyword,
                    'network' => $message->network,
                    'msisdn_alias' => $message->msisdnAlias,
                    'auto_response' => $autoResponse
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching message details', [
                'error' => $e->getMessage(),
                'message_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load message details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export received SMS to CSV
     * GET /api/mobile/received-sms/export
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Get filter parameters (same as messages endpoint)
            $filter = $request->input('filter', 'all');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $startTime = $request->input('start_time', '00:00');
            $endTime = $request->input('end_time', '23:59');

            // Build query (same logic as messages)
            $query = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid);

            // Apply filters...
            if ($filter === '-1') {
                $query->whereRaw("LOWER(msg) LIKE '%stop%'")
                    ->whereIn('dest', ['60300', '80809']);
            } elseif ($filter === '-2') {
                $query->whereRaw("LOWER(msg) LIKE '%stop%'")
                    ->where('dest', '447786201088');
            } elseif (is_numeric($filter)) {
                $itaggInstance = DB::table('itagg_instance')
                    ->join('smsshortcodes', 'itagg_instance.smsshortcodes_id', '=', 'smsshortcodes.id')
                    ->where('itagg_instance.id', $filter)
                    ->first();

                if ($itaggInstance) {
                    if ($itaggInstance->keyword !== '*') {
                        $query->where('keyword', 'LIKE', $itaggInstance->keyword);
                    }
                    $query->where('dest', 'LIKE', $itaggInstance->number);
                }
            }

            // Apply date filter
            if ($startDate && $endDate) {
                $startDateTime = str_replace('-', '', $startDate) . str_replace(':', '', $startTime) . '00';
                $endDateTime = str_replace('-', '', $endDate) . str_replace(':', '', $endTime) . '59';
                $query->whereBetween('recieved', [$startDateTime, $endDateTime]);
            }

            // Limit export to 10000 records
            $messages = $query->select('source', 'msg', 'recieved', 'dest')
                ->orderBy('recieved', 'DESC')
                ->limit(10000)
                ->get();

            // Generate CSV content
            $csvData = "From,Message,Date Received,Received To\n";
            foreach ($messages as $msg) {
                $csvData .= sprintf(
                    '"%s","%s","%s","%s"' . "\n",
                    $msg->source,
                    str_replace('"', '""', urldecode($msg->msg)),
                    $this->formatReceivedDateLong($msg->recieved),
                    $msg->dest
                );
            }

            // Generate filename
            $filename = 'received_sms_' . date('Y-m-d_His') . '.csv';

            // Save to public folder temporarily
            $folderPath = public_path('exports');
            if (!file_exists($folderPath)) {
                mkdir($folderPath, 0755, true);
            }
            $filePath = $folderPath . '/' . $filename;
            file_put_contents($filePath, $csvData);

            return response()->json([
                'success' => true,
                'message' => 'Export generated successfully',
                'data' => [
                    'download_url' => asset('exports/' . $filename),
                    'filename' => $filename,
                    'record_count' => count($messages)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error exporting received SMS', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for received SMS
     * GET /api/mobile/received-sms/stats
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            $bigid = $user->bigid;

            // Total messages
            $totalMessages = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid)
                ->count();

            // Messages today
            $todayStart = date('Ymd') . '000000';
            $todayEnd = date('Ymd') . '235959';
            $todayMessages = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid)
                ->whereBetween('recieved', [$todayStart, $todayEnd])
                ->count();

            // Messages this week
            $weekStart = date('Ymd', strtotime('monday this week')) . '000000';
            $weekEnd = date('Ymd', strtotime('sunday this week')) . '235959';
            $weekMessages = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid)
                ->whereBetween('recieved', [$weekStart, $weekEnd])
                ->count();

            // Messages this month
            $monthStart = date('Ym01') . '000000';
            $monthEnd = date('Ymt') . '235959';
            $monthMessages = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid)
                ->whereBetween('recieved', [$monthStart, $monthEnd])
                ->count();

            // STOP messages count
            $stopMessages = DB::table('itagg_incominglog')
                ->where('user_bigid', $bigid)
                ->whereRaw("LOWER(msg) LIKE '%stop%'")
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'total_messages' => $totalMessages,
                    'today_messages' => $todayMessages,
                    'week_messages' => $weekMessages,
                    'month_messages' => $monthMessages,
                    'stop_messages' => $stopMessages
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching received SMS stats', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format received date for display
     */
    private function formatReceivedDate($dateString)
    {
        if (empty($dateString) || strlen($dateString) < 14) {
            return 'Unknown';
        }

        try {
            $year = substr($dateString, 0, 4);
            $month = substr($dateString, 4, 2);
            $day = substr($dateString, 6, 2);
            $hour = substr($dateString, 8, 2);
            $minute = substr($dateString, 10, 2);
            $second = substr($dateString, 12, 2);

            $carbon = Carbon::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $hour:$minute:$second");
            return $carbon->format('d M Y H:i');
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Format received date for long display
     */
    private function formatReceivedDateLong($dateString)
    {
        if (empty($dateString) || strlen($dateString) < 14) {
            return 'Unknown';
        }

        try {
            $year = substr($dateString, 0, 4);
            $month = substr($dateString, 4, 2);
            $day = substr($dateString, 6, 2);
            $hour = substr($dateString, 8, 2);
            $minute = substr($dateString, 10, 2);
            $second = substr($dateString, 12, 2);

            $carbon = Carbon::createFromFormat('Y-m-d H:i:s', "$year-$month-$day $hour:$minute:$second");
            return $carbon->format('l, jS F Y g:i A');
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get auto-response for a message (if any)
     */
    private function getAutoResponse($message)
    {
        // Check if there was an auto-response sent for this incoming message
        // This would typically be tracked in the smsg_log table
        // For now, return null as auto-response tracking may vary by implementation
        return [
            'sent' => false,
            'message' => null,
            'sent_at' => null
        ];
    }
}
