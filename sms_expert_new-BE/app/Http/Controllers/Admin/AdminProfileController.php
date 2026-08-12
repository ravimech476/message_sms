<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

/**
 * Self-service admin profile actions for the currently logged-in admin user.
 * Lets staff change their own password without needing a super-admin to edit
 * the full user record.
 */
class AdminProfileController extends Controller
{
    /**
     * Show the change-password form.
     */
    public function showChangePassword()
    {
        $admin = Session::get('admin_user');
        if (!$admin || !isset($admin['id'])) {
            return redirect()->route('admin.login.show');
        }

        return view('admin.profile.change-password', ['admin' => $admin]);
    }

    /**
     * Handle the change-password submit.
     *
     * Verifies the current password (supports both plain-text legacy and md5
     * hash, matching AdminAuthController login logic). Writes the new password
     * as md5 so subsequent logins continue to work.
     */
    public function changePassword(Request $request)
    {
        $admin = Session::get('admin_user');
        if (!$admin || !isset($admin['id'])) {
            return redirect()->route('admin.login.show');
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'The new password and confirmation do not match.',
            'password.min'       => 'The new password must be at least 8 characters.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $user = User::find($admin['id']);
        if (!$user || $user->login_type !== 'admin') {
            Session::flush();
            return redirect()->route('admin.login.show')->with('error', 'Session expired. Please login again.');
        }

        $currentInput = (string) $request->input('current_password');
        $passwordMatch = $user->pword === $currentInput
                      || $user->pword === md5($currentInput);

        if (!$passwordMatch) {
            return back()->withErrors([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $newInput = (string) $request->input('password');
        if ($user->pword === $newInput || $user->pword === md5($newInput)) {
            return back()->withErrors([
                'password' => 'New password cannot be the same as the current password.',
            ]);
        }

        DB::table('users')
            ->where('id', $user->id)
            ->update(['pword' => md5($newInput)]);

        Log::info('Admin self-service password change', [
            'admin_id'       => $user->id,
            'admin_username' => $user->uname,
            'ip'             => $request->ip(),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}
