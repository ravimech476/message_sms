<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Models\User;
use App\Models\CustomerSetting;
use App\Models\CustomerMaintenance;
use Illuminate\Support\Facades\DB;

class CustomerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next)
    {
        $userInfo = Session::get('user_info');

        if (!isset($userInfo['bigid'])) {
            // Remember the page the user was trying to reach so we can send them back
            // there after they log in. redirect()->guest() stores the current full URL
            // as 'url.intended' (for GET routes), which redirect()->intended() in
            // LoginController then redirects to — falling back to /dashboard only when
            // there is no intended URL (e.g. a direct visit to the login page).
            return redirect()->guest('/')->with('error', 'You need to log in to access this page.');
        }

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
            return redirect()->away(config('domains.old_sms_expert_dashboard_url'));
        }

        if (auth()->check() || auth()->user()->login_type === 'customer' || auth()->user()->login_type === '') {

            // Check maintenance mode for customers
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
                \Log::warning('Maintenance mode check failed: ' . $e->getMessage());
            }

            // OLD-SYSTEM PARITY (ContractManager gatekeeper, cp2_contracts.inc): if a
            // re-sign reason is set against the account (e.g. a key profile element was
            // changed), force the customer onto the contracts page until they sign.
            // The contracts pages, logout, login and maintenance are allowed through to
            // avoid a redirect loop. Gating on the reason (not on agreedcontracts being
            // NULL) means only customers who actually need to re-sign are blocked.
            if (!$request->routeIs('customer.contracts.*', '*logout*', 'login', 'customer.maintenance')) {
                try {
                    $resignReason = DB::table('useroption')
                        ->where('userref', $userInfo['bigid'])
                        ->value('agreedcontracts_description');

                    if (!empty($resignReason)) {
                        return redirect()->route('customer.contracts.index')
                            ->with('error', 'Please read and re-sign your contract to continue: ' . $resignReason);
                    }
                } catch (\Exception $e) {
                    \Log::warning('Contract gatekeeper check failed: ' . $e->getMessage());
                }
            }

            return $next($request);
        }

        return redirect('/');
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
