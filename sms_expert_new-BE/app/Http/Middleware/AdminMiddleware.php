<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Session;


class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userInfo = Session::get('user_info');

        if (!isset($userInfo['bigid'])) {
            // Remember the admin page the user was trying to reach so login can send
            // them back there (redirect()->intended()), instead of always the dashboard.
            return redirect()->guest('/')->with('error', 'You need to log in to access this page.');
        }

        if (auth()->check() && auth()->user()->login_type === 'admin') {
            return $next($request);
        }
        
        return redirect('/'); 
    }
}
