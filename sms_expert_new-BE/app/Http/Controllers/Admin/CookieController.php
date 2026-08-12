<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

class CookieController extends Controller
{
    /**
     * Clear all cookies and session data in the admin panel.
     */
    public function clearCookiesAndSession(Request $request)
    {
        $cookies = $request->cookies->all();

        $response = response()->json([
            'message' => 'Admin cookies and session data have been cleared.'
        ]);

        foreach ($cookies as $name => $value) {
            $response->withCookie(Cookie::forget($name));
        }

        Session::flush();

        return $response;
    }
}
