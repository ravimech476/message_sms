<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\User;
use App\Models\IpAddressResstriction;
use App\Models\UsersSessionLog;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;

/**
 * Mobile App Authentication Controller
 * 
 * Handles mobile app login/logout with API token authentication
 * Mirrors the web LoginController logic with JSON responses
 */
class AuthController extends Controller
{
    /**
     * Mobile App Login
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @bodyParam userName string required The username. Example: testuser
     * @bodyParam password string required The password. Example: password123
     * @bodyParam device_name string optional Device name for token. Example: iPhone 14 Pro
     * @bodyParam device_id string optional Unique device identifier. Example: ABC123XYZ
     * @bodyParam fcm_token string optional Firebase Cloud Messaging token for push notifications
     */
    public function login(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'userName' => 'required|string',
                'password' => 'required|string',
                'device_name' => 'nullable|string|max:255',
                'device_id' => 'nullable|string|max:255',
                'fcm_token' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find user by username
            $user = User::where('uname', $request->userName)->first();

            // Check credentials
            if (!$user || $user->pword !== $request->password) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials',
                    'errors' => [
                        'userName' => ['Username and password do not match our records.']
                    ]
                ], 401);
            }

            // ✅ Check if user is customer type (mobile app is for customers only)
            if (!is_null($user->login_type) && $user->login_type === 'admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'Access denied',
                    'errors' => [
                        'userName' => ['Admin users cannot login via mobile app.']
                    ]
                ], 403);
            }

            // ✅ Check IP restriction
            if ($user->ip_address_restriction == 1) {
                $currentIp = $request->ip();
                $ipRestricted = IpAddressResstriction::where('ip_address', $currentIp)
                    ->where('bigid', $user->bigid)
                    ->where('status', 1)
                    ->exists();

                if ($ipRestricted) {
                    return response()->json([
                        'status' => false,
                        'message' => 'IP Restricted',
                        'errors' => [
                            'ip' => ['Your access has been denied as you are trying to access the system from a restricted IP address.']
                        ]
                    ], 403);
                }
            }

            // ✅ Check if account is disabled
            if ($user->bit_disabled == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Account Disabled',
                    'errors' => [
                        'account' => ['Your account has been disabled. Please contact support.']
                    ]
                ], 403);
            }

            // ✅ Check lockout status
            $lockoutStatus = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->select('profileupdate_lockout', 'clientcommfail')
                ->first();

            if ($lockoutStatus) {
                if ($lockoutStatus->clientcommfail === 'y') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Account Locked',
                        'errors' => [
                            'account' => ['Your account is locked. Please contact support.']
                        ]
                    ], 403);
                }

                if ($lockoutStatus->profileupdate_lockout == '1') {
                    return response()->json([
                        'status' => false,
                        'message' => 'Profile Update Required',
                        'errors' => [
                            'profile' => ['Please update your profile to continue.']
                        ],
                        'requires_profile_update' => true
                    ], 403);
                }
            }

            // ✅ Check maintenance mode
            $maintenanceCheck = $this->checkMaintenanceMode($user);
            if ($maintenanceCheck['is_maintenance']) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maintenance Mode',
                    'maintenance' => [
                        'enabled' => true,
                        'message' => $maintenanceCheck['message'],
                        'end_time' => $maintenanceCheck['end_time'] 
                            ? Carbon::parse($maintenanceCheck['end_time'])->toIso8601String() 
                            : null,
                    ]
                ], 503);
            }

            // ✅ Log customer session
            $timestamp = Carbon::now('Europe/London')->format('YmdHis');
            $sessionLog = UsersSessionLog::create([
                'big_id' => $user->bigid,
                'ip_address' => $request->ip(),
                'itaggcustid' => $user->bigid,
                'status' => 0,
                'login_date' => $timestamp,
                'device_name' => $request->device_name ?? 'Mobile App',
                'device_id' => $request->device_id,
            ]);

            // ✅ Update FCM token if provided
            if ($request->filled('fcm_token')) {
                $this->updateFcmToken($user, $request->fcm_token, $request->device_id);
            }

            // ✅ Generate API token using Laravel Sanctum
            $deviceName = $request->device_name ?? 'Mobile App - ' . $request->ip();
            
            // Revoke old tokens for this device (optional - for single device login)
            if ($request->filled('device_id')) {
                $user->tokens()->where('name', 'like', '%' . $request->device_id . '%')->delete();
            }
            
            $token = $user->createToken($deviceName . ' | ' . ($request->device_id ?? 'unknown'))->plainTextToken;

            // ✅ Calculate wallet balance
            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

            // ✅ Get user permissions/settings
            $userSettings = $this->getUserSettings($user);

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'bigid' => $user->bigid,
                        'username' => $user->uname,
                        'userref' => $user->userref,
                        'contact_name' => $user->contactname,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'company_name' => $user->companyname,
                        'login_type' => $user->login_type ?? 'customer',
                        'wallet_balance' => round($walletBalance, 2),
                        'smsg_wallet' => round($user->smsg_wallet, 2),
                        'dashboard_access' => $user->dashboardaccess ?? 'mca',
                        'created_at' => $user->created_at,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'session_id' => $sessionLog->id ?? null,
                    'settings' => $userSettings,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Login Error: ' . $ex->getMessage(), [
                'trace' => $ex->getTraceAsString(),
                'username' => $request->userName ?? 'unknown',
            ]);

            return response()->json([
                'status' => false,
                'message' => 'An error occurred during login',
                'errors' => [
                    'server' => ['Unable to process login. Please try again later.']
                ]
            ], 500);
        }
    }

    /**
     * Mobile App Logout
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                // Update session log
                $sessionLog = UsersSessionLog::where('big_id', $user->bigid)
                    ->where('status', 0)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($sessionLog) {
                    $sessionLog->update([
                        'status' => 1,
                        'logout_date' => Carbon::now('Europe/London')->format('YmdHis'),
                    ]);
                }

                // Revoke current token
                $request->user()->currentAccessToken()->delete();
            }

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Logout Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Logout failed',
                'errors' => ['server' => ['Unable to logout. Please try again.']]
            ], 500);
        }
    }

    /**
     * Logout from all devices
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logoutAll(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                // Revoke all tokens
                $user->tokens()->delete();

                // Update all session logs
                UsersSessionLog::where('big_id', $user->bigid)
                    ->where('status', 0)
                    ->update([
                        'status' => 1,
                        'logout_date' => Carbon::now('Europe/London')->format('YmdHis'),
                    ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Logged out from all devices successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Logout All Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Get current user profile
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Refresh user data
            $user = User::where('id', $user->id)->first();

            // Calculate wallet balance
            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

            // Get user settings
            $userSettings = $this->getUserSettings($user);

            // Check maintenance mode
            $maintenanceCheck = $this->checkMaintenanceMode($user);

            return response()->json([
                'status' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'bigid' => $user->bigid,
                        'username' => $user->uname,
                        'userref' => $user->userref,
                        'contact_name' => $user->contactname,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'company_name' => $user->companyname,
                        'address1' => $user->address1,
                        'address2' => $user->address2,
                        'city' => $user->city,
                        'county' => $user->county,
                        'postcode' => $user->postcode,
                        'country' => $user->country,
                        'login_type' => $user->login_type ?? 'customer',
                        'wallet_balance' => round($walletBalance, 2),
                        'smsg_wallet' => round($user->smsg_wallet, 2),
                        'dashboard_access' => $user->dashboardaccess ?? 'mca',
                        'is_disabled' => $user->bit_disabled == 1,
                        'ip_restricted' => $user->ip_address_restriction == 1,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                    'settings' => $userSettings,
                    'maintenance' => $maintenanceCheck['is_maintenance'] ? [
                        'enabled' => true,
                        'message' => $maintenanceCheck['message'],
                        'end_time' => $maintenanceCheck['end_time'],
                    ] : null,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Profile Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve profile'
            ], 500);
        }
    }

    /**
     * Refresh authentication token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Get current token name
            $currentToken = $request->user()->currentAccessToken();
            $tokenName = $currentToken->name ?? 'Mobile App';

            // Delete current token
            $currentToken->delete();

            // Create new token
            $newToken = $user->createToken($tokenName)->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Token Refresh Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Update FCM token for push notifications
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePushToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string|max:500',
                'device_id' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $this->updateFcmToken($user, $request->fcm_token, $request->device_id);

            return response()->json([
                'status' => true,
                'message' => 'Push notification token updated successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('FCM Token Update Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update push token'
            ], 500);
        }
    }

    /**
     * Change password
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation Error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Verify current password
            if ($user->pword !== $request->current_password) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid current password',
                    'errors' => [
                        'current_password' => ['The current password is incorrect.']
                    ]
                ], 422);
            }

            // Update password
            $user->pword = $request->new_password;
            $user->save();

            // Optionally revoke all other tokens for security
            // $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Password Change Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Check authentication status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAuth(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Not authenticated',
                    'authenticated' => false
                ], 401);
            }

            // Refresh user from database
            $user = User::where('id', $user->id)->first();

            // Check if account is still valid
            if ($user->bit_disabled == 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Account disabled',
                    'authenticated' => false,
                    'reason' => 'account_disabled'
                ], 403);
            }

            // Check lockout
            $lockoutStatus = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->select('profileupdate_lockout', 'clientcommfail')
                ->first();

            if ($lockoutStatus && $lockoutStatus->clientcommfail === 'y') {
                return response()->json([
                    'status' => false,
                    'message' => 'Account locked',
                    'authenticated' => false,
                    'reason' => 'account_locked'
                ], 403);
            }

            // Check maintenance
            $maintenanceCheck = $this->checkMaintenanceMode($user);

            $walletBalance = $user->smsg_wallet - $user->smsg_server1_sent - $user->smsg_server2_sent;

            return response()->json([
                'status' => true,
                'message' => 'Authenticated',
                'authenticated' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'bigid' => $user->bigid,
                        'username' => $user->uname,
                        'contact_name' => $user->contactname,
                        'wallet_balance' => round($walletBalance, 2),
                    ],
                    'maintenance' => $maintenanceCheck['is_maintenance'] ? [
                        'enabled' => true,
                        'message' => $maintenanceCheck['message'],
                    ] : null,
                    'requires_profile_update' => $lockoutStatus && $lockoutStatus->profileupdate_lockout == '1',
                ],
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Check Auth Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Authentication check failed',
                'authenticated' => false
            ], 500);
        }
    }

    /**
     * Check if customer is in maintenance mode
     * 
     * @param User $user
     * @return array
     */
    private function checkMaintenanceMode($user): array
    {
        $result = [
            'is_maintenance' => false,
            'message' => '',
            'end_time' => null,
        ];

        try {
            // First check global maintenance mode
            $globalMaintenance = CustomerSetting::getValue('global_maintenance_mode', false);

            if ($globalMaintenance) {
                $result['is_maintenance'] = true;
                $result['message'] = CustomerSetting::getValue('maintenance_message', 'The system is currently under maintenance. Please try again later.');
                return $result;
            }

            // Check customer-specific maintenance
            $customerMaintenance = CustomerMaintenance::where('is_enabled', true)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('user_bigid', $user->bigid);
                })
                ->where(function ($q) {
                    $q->whereNull('start_time')
                        ->orWhere('start_time', '<=', now());
                })
                ->where(function ($q) {
                    $q->whereNull('end_time')
                        ->orWhere('end_time', '>=', now());
                })
                ->first();

            if ($customerMaintenance) {
                $result['is_maintenance'] = true;
                $result['message'] = $customerMaintenance->maintenance_message
                    ?: CustomerSetting::getValue('maintenance_message', 'The system is currently under maintenance. Please try again later.');
                $result['end_time'] = $customerMaintenance->end_time;
            }

        } catch (\Exception $e) {
            // If tables don't exist, continue without maintenance check
            \Log::warning('Mobile maintenance mode check failed: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get user settings/preferences
     * 
     * @param User $user
     * @return array
     */
    private function getUserSettings($user): array
    {
        $settings = [];

        try {
            // Get user options
            $userOptions = DB::table('useroption')
                ->where('userref', $user->bigid)
                ->first();

            if ($userOptions) {
                $settings = [
                    'notifications_enabled' => true,
                    'profile_update_required' => $userOptions->profileupdate_lockout == '1',
                ];
            }

        } catch (\Exception $e) {
            \Log::warning('Failed to get user settings: ' . $e->getMessage());
        }

        return $settings;
    }

    /**
     * Update FCM token for user
     * 
     * @param User $user
     * @param string $fcmToken
     * @param string|null $deviceId
     */
    private function updateFcmToken($user, string $fcmToken, ?string $deviceId = null): void
    {
        try {
            // You can store FCM tokens in a separate table or user column
            // For now, we'll store in a user_devices table or user meta
            
            DB::table('user_fcm_tokens')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'device_id' => $deviceId ?? 'default',
                ],
                [
                    'fcm_token' => $fcmToken,
                    'updated_at' => now(),
                ]
            );

        } catch (\Exception $e) {
            // Table might not exist, log and continue
            \Log::warning('Failed to update FCM token: ' . $e->getMessage());
        }
    }
}
