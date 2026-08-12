<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = null): Response
    {
        $adminSession = Session::get('admin_user');
        
        if (!$adminSession) {
            return redirect('/')->with('error', 'Please login to continue.');
        }

        // Get the admin user
        $adminUser = User::with('adminPermissions')->find($adminSession['id']);
        
        if (!$adminUser) {
            Session::forget('admin_user');
            return redirect('/')->with('error', 'Admin account not found.');
        }

        // Check if admin user is active
        if ($adminUser->bit_disabled) {
            Session::forget('admin_user');
            return redirect('/')->with('error', 'Your account has been deactivated.');
        }

        // Check if user is actually an admin
        if ($adminUser->login_type !== 'admin') {
            Session::forget('admin_user');
            return redirect('/')->with('error', 'You do not have admin access.');
        }

        // Check specific permission if provided
        if ($permission && !$adminUser->hasPermission($permission)) {
            if ($request->ajax()) {
                return response()->json(['error' => 'You do not have permission to perform this action.'], 403);
            }
            return redirect()->route('admin.dashboard')->with('error', 'You do not have permission to access this page.');
        }

        // Share admin user with all views
        view()->share('currentAdminUser', $adminUser);

        return $next($request);
    }
}
