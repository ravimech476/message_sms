<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserOption;
use App\Models\UserReminder;

class WalletController extends Controller
{
    /**
     * Get SMS Wallet data and settings
     * GET /api/mobile/wallet
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $bigid = $user->bigid;

            // Get user with reminders and options
            $userData = User::with(['reminders', 'options'])
                ->where('bigid', $bigid)
                ->first();

            if (!$userData) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Calculate wallet balance
            $smsg_wallet = $userData->smsg_wallet ?? 0;
            $smsg_server1_sent = $userData->smsg_server1_sent ?? 0;
            $smsg_server2_sent = $userData->smsg_server2_sent ?? 0;
            $remaining_wallet = $smsg_wallet - $smsg_server1_sent - $smsg_server2_sent;

            // Get reminder settings
            $reminder = $userData->reminders->first();
            $reminderSettings = null;
            
            if ($reminder) {
                $reminderSettings = [
                    'email_reminder_enabled' => $reminder->reminderon === 'y',
                    'minimum_balance' => (float) ($reminder->numonremind ?? 0),
                    'reminder_period_days' => (int) ($reminder->reminderperiod ?? 1),
                ];
            }

            // Get options (immediate notification settings)
            $option = $userData->options->first();
            $immediateSettings = null;
            
            if ($option) {
                $immediateSettings = [
                    'immediate_email_enabled' => $option->immediateEmailReminderon === 'y',
                    'notification_email' => $option->immediateOutOfFundsNotificationEmail ?? '',
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Wallet data retrieved successfully',
                'data' => [
                    'user' => [
                        'name' => $userData->contactname,
                        'company' => $userData->busname,
                        'email' => $userData->contactemail,
                    ],
                    'wallet' => [
                        'balance' => round($remaining_wallet, 2),
                        'total_wallet' => round($smsg_wallet, 2),
                        'used' => round($smsg_server1_sent + $smsg_server2_sent, 2),
                        'currency' => 'GBP',
                        'currency_symbol' => '£',
                    ],
                    'daily_notification_settings' => $reminderSettings,
                    'immediate_notification_settings' => $immediateSettings,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching wallet data: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch wallet data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update SMS Wallet notification settings
     * POST /api/mobile/wallet/settings
     */
    public function updateSettings(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $bigid = $user->bigid;

            // Validate request
            $request->validate([
                // Daily notification settings
                'email_reminder_enabled' => 'sometimes|boolean',
                'minimum_balance' => 'sometimes|numeric|min:0',
                'reminder_period_days' => 'sometimes|integer|min:1|max:14',
                // Immediate notification settings
                'immediate_email_enabled' => 'sometimes|boolean',
                'notification_email' => 'sometimes|nullable|email',
            ]);

            // Update reminder settings
            $reminder = UserReminder::where('usersbigidref', $bigid)->first();
            
            if ($reminder) {
                if ($request->has('email_reminder_enabled')) {
                    $reminder->reminderon = $request->email_reminder_enabled ? 'y' : 'n';
                }
                if ($request->has('minimum_balance')) {
                    $reminder->numonremind = $request->minimum_balance;
                }
                if ($request->has('reminder_period_days')) {
                    $reminder->reminderperiod = $request->reminder_period_days;
                }
                $reminder->save();
            }

            // Update options (immediate notification settings)
            $option = UserOption::where('userref', $bigid)->first();
            
            if ($option) {
                if ($request->has('immediate_email_enabled')) {
                    $option->immediateEmailReminderon = $request->immediate_email_enabled ? 'y' : 'n';
                }
                if ($request->has('notification_email')) {
                    $option->immediateOutOfFundsNotificationEmail = $request->notification_email;
                }
                $option->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Wallet settings updated successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating wallet settings: ' . $e->getMessage());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to update wallet settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
