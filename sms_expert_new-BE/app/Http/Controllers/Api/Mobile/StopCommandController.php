<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile App STOP Commands Controller
 * 
 * Handles STOP command configuration and optouts management
 */
class StopCommandController extends Controller
{
    /**
     * Get STOP command settings for the authenticated user
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

            // Get user options from useroption table
            $userOption = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->first();

            // Get STOP command settings using correct column names
            $stopUrl = '';
            $stopEmail = '';
            $stopName = '';
            
            if ($userOption) {
                $stopUrl = $userOption->stop_command_url ?? '';
                // OLD-migrated rows store the email/name URL-encoded (e.g. steve%40itagg.com);
                // decode for display so the app shows steve@itagg.com. Raw values pass through.
                $stopEmail = rawurldecode($userOption->stopcommand_contactemail ?? '');
                $stopName = urldecode($userOption->stopcommand_contactname ?? '');
            }

            return response()->json([
                'status' => true,
                'message' => 'STOP command settings retrieved successfully',
                'data' => [
                    'stop_url' => $stopUrl,
                    'stop_email' => $stopEmail,
                    'stop_name' => $stopName,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Stop Command Index Error: ' . $ex->getMessage());
            Log::error('Mobile Stop Command Index Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load STOP command settings',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Update STOP command settings
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'stop_url' => 'nullable|url|max:200',
                'stop_email' => 'nullable|email|max:50',
                'stop_name' => 'nullable|string|max:50',
            ], [
                'stop_url.url' => 'Please enter a valid URL (must start with http:// or https://)',
                'stop_url.max' => 'URL must not exceed 200 characters',
                'stop_email.email' => 'Please enter a valid email address',
                'stop_email.max' => 'Email must not exceed 50 characters',
                'stop_name.max' => 'Contact name must not exceed 50 characters',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $stopUrl = $request->input('stop_url', '');
            $stopEmail = $request->input('stop_email', '');
            $stopName = $request->input('stop_name', '');

            // Update or insert into useroption table using correct column names
            $exists = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->exists();

            if ($exists) {
                DB::table('useroption')
                    ->where('userref', $user->bigid)
                    ->update([
                        'stop_command_url' => $stopUrl,
                        'stopcommand_contactemail' => $stopEmail,
                        'stopcommand_contactname' => $stopName,
                    ]);
            } else {
                DB::table('useroption')->insert([
                    'userref' => $user->bigid,
                    'stop_command_url' => $stopUrl,
                    'stopcommand_contactemail' => $stopEmail,
                    'stopcommand_contactname' => $stopName,
                ]);
            }

            // useroption changed → rebuild this account's cache (Phase 2).
            app(\App\Services\TableCache::class)->rebuildUseroption($user->bigid);

            // Log the update
            Log::info('STOP command settings updated', [
                'user_id' => $user->id,
                'bigid' => $user->bigid,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'STOP command settings updated successfully',
                'data' => [
                    'stop_url' => $stopUrl,
                    'stop_email' => $stopEmail,
                    'stop_name' => $stopName,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Stop Command Update Error: ' . $ex->getMessage());
            Log::error('Mobile Stop Command Update Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update STOP command settings',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Get STOP command statistics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get opt-out statistics from blacklist/stops table
            // This is a placeholder - adjust based on your actual table structure
            $totalOptouts = 0;
            $thisMonth = 0;
            $thisWeek = 0;
            
            try {
                // Try to get stats from stops or blacklist table
                $totalOptouts = DB::table('stops')
                    ->where('userref', $user->bigid)
                    ->count();
                    
                $thisMonth = DB::table('stops')
                    ->where('userref', $user->bigid)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->count();
                    
                $thisWeek = DB::table('stops')
                    ->where('userref', $user->bigid)
                    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count();
            } catch (\Exception $e) {
                // Table might not exist or have different structure
                Log::warning('Could not fetch STOP stats: ' . $e->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'STOP command statistics retrieved successfully',
                'data' => [
                    'total_optouts' => $totalOptouts,
                    'this_month' => $thisMonth,
                    'this_week' => $thisWeek,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Stop Command Stats Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load STOP command statistics',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
