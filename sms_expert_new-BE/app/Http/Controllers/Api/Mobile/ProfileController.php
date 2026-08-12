<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mobile App Profile Controller
 * 
 * Handles user profile for the mobile application
 */
class ProfileController extends Controller
{
    /**
     * Get user profile data
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
            
            $bigid = $user->bigid;
            
            Log::info('Mobile Profile - User bigid: ' . $bigid);

            // Get user profile from users table
            $profile = DB::table('users')
                ->where('bigid', $bigid)
                ->first();

            if (!$profile) {
                return response()->json([
                    'status' => false,
                    'message' => 'Profile not found'
                ], 404);
            }

            // Get user options for additional settings (table: useroption, key: userref)
            $userOption = DB::table('useroption')
                ->where('userref', $bigid)
                ->first();

            // Get IP whitelist
            $ipList = DB::table('ip_address_resstrictions')
                ->where('bigid', $bigid)
                ->where('status', 1)
                ->pluck('ip_address')
                ->toArray();

            // Format expiry date
            $expiryDate = 'Not Set';
            // Check if user option has expiry info
            if ($userOption && isset($userOption->expiry_date) && $userOption->expiry_date && $userOption->expiry_date != '0000-00-00') {
                $expiryDate = date('d M Y', strtotime($userOption->expiry_date));
            }

            // Get daily SMS limit - check various possible column names
            $dailySmsLimit = 100000; // Default
            if ($userOption) {
                if (isset($userOption->dailylimit) && $userOption->dailylimit) {
                    $dailySmsLimit = (int)$userOption->dailylimit;
                } elseif (isset($userOption->daily_limit) && $userOption->daily_limit) {
                    $dailySmsLimit = (int)$userOption->daily_limit;
                }
            }

            // Get default sender ID
            $defaultSenderId = 'MYBRANDNAME';
            if ($userOption && isset($userOption->defaultsenderid) && $userOption->defaultsenderid) {
                $defaultSenderId = $userOption->defaultsenderid;
            } elseif ($userOption && isset($userOption->default_sender_id) && $userOption->default_sender_id) {
                $defaultSenderId = $userOption->default_sender_id;
            }

            // Get service description
            $serviceDescription = 'My SMS Expert Account.';
            if ($userOption && isset($userOption->explanation) && $userOption->explanation) {
                $serviceDescription = $userOption->explanation;
            } elseif ($profile->anondesc) {
                $serviceDescription = $profile->anondesc;
            }

            return response()->json([
                'status' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'profile' => [
                        'service_description' => $serviceDescription,
                        'business_name' => $profile->busname ?? '',
                        'contact_name' => $profile->contactname ?? '',
                        'address1' => $profile->address1 ?? '',
                        'address2' => $profile->address2 ?? '',
                        'town' => $profile->town ?? '',
                        'country' => $profile->country ?? '',
                        'postcode' => $profile->pcode ?? '',
                        'mobile_number' => $profile->mobilenumber ?? '',
                        'phone_number' => $profile->phone ?? '',
                        'email' => $profile->contactemail ?? '',
                        'default_sender_id' => $defaultSenderId,
                        'account_expiry' => $expiryDate,
                        'username' => $profile->uname ?? '',
                    ],
                    'user' => [
                        'id' => $user->id,
                        'bigid' => $bigid,
                        'username' => $profile->uname ?? '',
                        'contact_name' => $profile->contactname ?? '',
                        'company_name' => $profile->busname ?? '',
                        'email' => $profile->contactemail ?? '',
                        'dashboard_access' => $profile->dashboardaccess ?? 'mca',
                    ],
                    'limits' => [
                        'daily_sms_limit' => $dailySmsLimit,
                    ],
                    'ip_whitelist' => $ipList,
                    'push_delivery_active' => false,
                ],
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Profile Error: ' . $ex->getMessage());
            Log::error('Mobile Profile Trace: ' . $ex->getTraceAsString());

            return response()->json([
                'status' => false,
                'message' => 'Failed to load profile',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_description' => 'required|string|max:1000',
                'business_name' => 'required|string|max:255',
                'contact_name' => 'required|string|max:255',
                'address1' => 'required|string|max:255',
                'address2' => 'nullable|string|max:255',
                'town' => 'required|string|max:255',
                'country' => 'nullable|string|max:255',
                'postcode' => 'required|string|max:20',
                'mobile_number' => 'nullable|string|max:20',
                'phone_number' => 'required|string|max:20',
                'email' => 'required|email|max:255',
                'default_sender_id' => 'nullable|string|max:11',
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

            // Update users table
            DB::table('users')
                ->where('bigid', $bigid)
                ->update([
                    'busname' => $request->business_name,
                    'contactname' => $request->contact_name,
                    'address1' => $request->address1,
                    'address2' => $request->address2 ?? '',
                    'town' => $request->town,
                    'country' => $request->country ?? '',
                    'pcode' => $request->postcode,
                    'mobilenumber' => $request->mobile_number ?? '',
                    'phone' => $request->phone_number,
                    'contactemail' => $request->email,
                    'anondesc' => $request->service_description,
                ]);

            // Update useroption table for sender ID if record exists
            $existingOption = DB::table('useroption')
                ->where('userref', $bigid)
                ->first();

            if ($existingOption) {
                $updateData = ['explanation' => $request->service_description];
                
                // Check which column exists for sender ID
                if (isset($existingOption->defaultsenderid)) {
                    $updateData['defaultsenderid'] = $request->default_sender_id ?? 'MYBRANDNAME';
                } elseif (isset($existingOption->default_sender_id)) {
                    $updateData['default_sender_id'] = $request->default_sender_id ?? 'MYBRANDNAME';
                }

                DB::table('useroption')
                    ->where('userref', $bigid)
                    ->update($updateData);
            }

            return response()->json([
                'status' => true,
                'message' => 'Profile updated successfully.',
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Profile Update Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to update profile',
                'error' => $ex->getMessage()
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
                'new_password' => 'required|string|min:6',
                'confirm_password' => 'required|string|same:new_password',
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

            // Get current password from users table
            $profile = DB::table('users')
                ->where('bigid', $bigid)
                ->first();

            // Check current password (plain text for legacy compatibility)
            if ($profile->pword !== $request->current_password) {
                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect',
                    'errors' => [
                        'current_password' => ['The current password is incorrect.']
                    ]
                ], 422);
            }

            // Update password
            DB::table('users')
                ->where('bigid', $bigid)
                ->update([
                    'pword' => $request->new_password,
                ]);

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully',
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Password Change Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to change password',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Add IP to whitelist
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addIp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required|ip',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please enter a valid IP address',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $bigid = $user->bigid;

            // Check if IP already exists
            $exists = DB::table('ip_address_resstrictions')
                ->where('bigid', $bigid)
                ->where('ip_address', $request->ip_address)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'This IP address is already in the list',
                ], 422);
            }

            // Add IP
            DB::table('ip_address_resstrictions')->insert([
                'bigid' => $bigid,
                'ip_address' => $request->ip_address,
                'status' => 1,
                'created' => now(),
            ]);

            // Get updated list
            $ipList = DB::table('ip_address_resstrictions')
                ->where('bigid', $bigid)
                ->where('status', 1)
                ->pluck('ip_address')
                ->toArray();

            return response()->json([
                'status' => true,
                'message' => 'IP address added successfully',
                'data' => $ipList,
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Add IP Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to add IP address',
                'error' => $ex->getMessage()
            ], 500);
        }
    }

    /**
     * Remove IP from whitelist
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeIp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ip_address' => 'required|ip',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Please provide a valid IP address',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $bigid = $user->bigid;

            // Remove IP (set status to 0 or delete)
            $deleted = DB::table('ip_address_resstrictions')
                ->where('bigid', $bigid)
                ->where('ip_address', $request->ip_address)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'status' => false,
                    'message' => 'IP address not found in the list',
                ], 404);
            }

            // Get updated list
            $ipList = DB::table('ip_address_resstrictions')
                ->where('bigid', $bigid)
                ->where('status', 1)
                ->pluck('ip_address')
                ->toArray();

            return response()->json([
                'status' => true,
                'message' => 'IP address removed successfully',
                'data' => $ipList,
            ], 200);

        } catch (\Throwable $ex) {
            Log::error('Mobile Remove IP Error: ' . $ex->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Failed to remove IP address',
                'error' => $ex->getMessage()
            ], 500);
        }
    }
}
