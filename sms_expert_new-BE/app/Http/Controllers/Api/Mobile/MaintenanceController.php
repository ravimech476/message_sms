<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;

/**
 * Mobile Maintenance Status Controller
 * 
 * Check if customer is in maintenance mode
 */
class MaintenanceController extends Controller
{
    /**
     * Check maintenance mode status for the user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'is_maintenance' => false,
                        'message' => '',
                        'end_time' => null,
                    ],
                ]);
            }

            $result = $this->checkMaintenanceMode($user);

            return response()->json([
                'status' => true,
                'message' => 'Maintenance status retrieved',
                'data' => $result,
            ], 200);

        } catch (\Throwable $ex) {
            \Log::error('Mobile Maintenance Check Error: ' . $ex->getMessage());

            // In case of error, assume not in maintenance
            return response()->json([
                'status' => true,
                'data' => [
                    'is_maintenance' => false,
                    'message' => '',
                    'end_time' => null,
                ],
            ], 200);
        }
    }

    /**
     * Check if customer is in maintenance mode
     */
    private function checkMaintenanceMode($user): array
    {
        $result = [
            'is_maintenance' => false,
            'message' => '',
            'end_time' => null,
        ];

        // First check global maintenance mode
        try {
            $globalMaintenance = CustomerSetting::getValue('global_maintenance_mode', false);
            
            if ($globalMaintenance) {
                $result['is_maintenance'] = true;
                $result['message'] = CustomerSetting::getValue(
                    'maintenance_message', 
                    'The app is currently under maintenance. Please try again later.'
                );
                return $result;
            }
        } catch (\Exception $e) {
            // CustomerSetting table may not exist, continue
            \Log::warning('Could not check global maintenance mode: ' . $e->getMessage());
        }

        // Check customer-specific maintenance
        try {
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
                    ?: 'Your account is currently under maintenance. Please try again later.';
                $result['end_time'] = $customerMaintenance->end_time 
                    ? $customerMaintenance->end_time->toIso8601String() 
                    : null;
            }
        } catch (\Exception $e) {
            // CustomerMaintenance table may not exist, continue
            \Log::warning('Could not check customer maintenance mode: ' . $e->getMessage());
        }

        return $result;
    }
}
