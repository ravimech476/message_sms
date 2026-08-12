<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userInfo = Session::get('user_info');
        
        // Skip if no user info or admin user
        if (!$userInfo || ($userInfo['login_type'] ?? null) === 'admin') {
            return $next($request);
        }

        try {
            // Check maintenance mode
            $maintenanceCheck = $this->checkMaintenanceMode($userInfo);
            
            if ($maintenanceCheck['is_maintenance']) {
                // Update session with latest maintenance info
                Session::put('maintenance_mode', [
                    'enabled' => true,
                    'message' => $maintenanceCheck['message'],
                    'end_time' => $maintenanceCheck['end_time'],
                ]);
                
                return redirect()->route('customer.maintenance');
            } else {
                // Clear maintenance mode from session if it was set
                Session::forget('maintenance_mode');
            }
        } catch (\Exception $e) {
            // If tables don't exist, continue without check
            \Log::warning('Maintenance mode middleware check failed: ' . $e->getMessage());
        }

        return $next($request);
    }

    /**
     * Check if customer is in maintenance mode
     */
    private function checkMaintenanceMode($userInfo): array
    {
        $result = [
            'is_maintenance' => false,
            'message' => '',
            'end_time' => null,
        ];

        // First check global maintenance mode
        $globalMaintenance = CustomerSetting::getValue('global_maintenance_mode', false);
        
        if ($globalMaintenance) {
            $result['is_maintenance'] = true;
            $result['message'] = CustomerSetting::getValue('maintenance_message', 'The site is currently under maintenance. Please try again later.');
            return $result;
        }

        // Get user from database
        $user = User::where('bigid', $userInfo['bigid'])->first();
        
        if (!$user) {
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
                ?: CustomerSetting::getValue('maintenance_message', 'The site is currently under maintenance. Please try again later.');
            $result['end_time'] = $customerMaintenance->end_time;
        }

        return $result;
    }
}
