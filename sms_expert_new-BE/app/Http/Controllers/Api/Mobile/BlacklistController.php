<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ItaggOutboundBlacklist;
use Carbon\Carbon;

/**
 * Mobile App Blacklist Controller
 * 
 * Handles STOP Blacklist operations for mobile app
 */
class BlacklistController extends Controller
{
    /**
     * Get all blacklisted numbers with statistics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $userref = $user->bigid;

            // Get all blacklisted numbers
            $blacklistData = ItaggOutboundBlacklist::where('users_bigid', $userref)
                ->orderBy('date_blocked', 'desc')
                ->get();

            // Calculate statistics
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $startOfWeek = $now->copy()->startOfWeek();

            $totalBlacklisted = $blacklistData->count();
            
            $addedThisMonth = $blacklistData->filter(function ($item) use ($startOfMonth) {
                return Carbon::parse($item->date_blocked)->gte($startOfMonth);
            })->count();
            
            $addedThisWeek = $blacklistData->filter(function ($item) use ($startOfWeek) {
                return Carbon::parse($item->date_blocked)->gte($startOfWeek);
            })->count();

            // Format items for response
            $items = $blacklistData->map(function ($item) {
                return [
                    'id' => $item->id,
                    'phone_number' => $item->msisdn,
                    'blocked_date' => Carbon::parse($item->date_blocked)->format('d M Y H:i'),
                    'blocked_date_raw' => $item->date_blocked,
                    'status' => 'blocked',
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Blacklist retrieved successfully',
                'data' => [
                    'items' => $items,
                    'statistics' => [
                        'total_blacklisted' => $totalBlacklisted,
                        'added_this_month' => $addedThisMonth,
                        'added_this_week' => $addedThisWeek,
                    ],
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Blacklist Index Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve blacklist',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Download blacklist as CSV data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function download(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $userref = $user->bigid;

            // Get all blacklisted numbers
            $blacklistData = ItaggOutboundBlacklist::where('users_bigid', $userref)
                ->orderBy('date_blocked', 'desc')
                ->get();

            if ($blacklistData->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No blacklist data available to download'
                ], 404);
            }

            // Build CSV content
            $csvData = [];
            $csvData[] = ['S.No', 'Mobile Number', 'Date Blocked'];
            
            $counter = 1;
            foreach ($blacklistData as $item) {
                $csvData[] = [
                    $counter++,
                    $item->msisdn ?? '',
                    Carbon::parse($item->date_blocked)->format('d-m-Y H:i:s'),
                ];
            }

            // Convert to CSV string
            $csvString = '';
            foreach ($csvData as $row) {
                $csvString .= implode(',', array_map(function($cell) {
                    // Escape cells containing commas or quotes
                    if (strpos($cell, ',') !== false || strpos($cell, '"') !== false) {
                        return '"' . str_replace('"', '""', $cell) . '"';
                    }
                    return $cell;
                }, $row)) . "\n";
            }

            return response()->json([
                'status' => true,
                'message' => 'Blacklist CSV generated successfully',
                'data' => [
                    'filename' => 'blacklist_report_' . date('Y-m-d') . '.csv',
                    'content' => base64_encode($csvString),
                    'mime_type' => 'text/csv',
                    'total_records' => count($csvData) - 1, // Exclude header
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Blacklist Download Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to generate blacklist CSV',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Unblock a phone number (remove from blacklist)
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblock(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $userref = $user->bigid;

            // Find the blacklist record
            $record = ItaggOutboundBlacklist::where('id', $id)
                ->where('users_bigid', $userref)
                ->first();

            if (!$record) {
                return response()->json([
                    'status' => false,
                    'message' => 'Blacklist record not found'
                ], 404);
            }

            $phoneNumber = $record->msisdn;
            
            // Delete the record
            $record->delete();

            Log::info('Mobile Blacklist Unblock: Number unblocked', [
                'user_ref' => $userref,
                'phone_number' => $phoneNumber,
                'record_id' => $id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Number unblocked successfully',
                'data' => [
                    'unblocked_number' => $phoneNumber,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Blacklist Unblock Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to unblock number',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
