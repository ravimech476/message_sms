<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;

class CampaignMiddleware
{
    public function handle($request, Closure $next)
    {
        // Must be logged in
        if (!Auth::check()) {
            return redirect('/')
                ->with('error', 'Please log in to access this page.');
        }

        $user = Auth::user();
        $userInfo = Session::get('user_info');

        // ADD THIS BLOCK (Migration flag check)
        $user = User::where('bigid', $userInfo['bigid'])->first();

        if ($user && $user->migration_flag === 'old') {

            // logout
            auth()->logout();

            // clear session
            Session::flush();

            // invalidate session
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // redirect to old SMS Expert System
            return redirect()->away(config('domains.old_sms_expert_campaign_url'));
        }

        // Must have session data
        if (!$userInfo || !isset($userInfo['login_type'])) {
            return redirect('/')
                ->with('error', 'Please log in to access this page.');
        }

        // Session must be 'campaign'
        if ($userInfo['login_type'] !== 'campaign') {
            return redirect('/')
                ->with('error', 'Unauthorized campaign access.');
        }

        // DB login_type must be customer or null
        if (!is_null($user->login_type) && $user->login_type !== 'customer') {
            return redirect('/')
                ->with('error', 'Only customer accounts can access the campaign manager.');
        }

        // Check maintenance mode for campaign users
        try {
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
            \Log::warning('Maintenance mode check failed in CampaignMiddleware: ' . $e->getMessage());
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
