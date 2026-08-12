<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Mail\ForgotPasswordMail;
use App\Services\Queue\EmailQueueService;
use App\Models\User;
use Exception;

class ForgotPasswordController extends Controller
{
    public function requestReset(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
        ]);

        $user = User::where('uname', $request->username)->first();

        if ($user) {
            // Send email to the user's contact email
            $contactEmail = $user->contactemail;
            $toAddress = $contactEmail;
            $ccAddress = '';
            $userName = $user->busname;
            $userId = $user->id;

            try {
                // Send confirmation email via RabbitMQ queue
                $emailQueueService = new EmailQueueService();
                $emailQueueService->queueEmail(
                    'App\\Mail\\ForgotPasswordMail',
                    $toAddress,
                    [
                        'to_address' => $toAddress,
                        'cc_address' => $ccAddress,
                        'user_name' => $userName,
                        'user_id' => $userId,
                    ],
                    $ccAddress ? [$ccAddress] : []
                );

                // Update the user's forgot password flags
                $user->update([
                    'forgot_password1_link' => 0,
                    'forgot_password1_submit' => 0,
                ]);

                Log::info("Forgot password email sent to user ID: $userId");
                return redirect('/')->with('success', 'Forgot password link has been sent your mail id');
            } catch (\Exception $e) {
                Log::error('Email sending failed: ' . $e->getMessage());
                return redirect('/')->with('error', 'Failed to send email.');
            }
        }

        return back()->with('error', 'Username not found.');
    }

    public function showResetPasswordForm($userId)
    {
        $user = User::find($userId);

        if (!$user) {
            return redirect('/')->with('error', 'User not found.');
        }

        if ($user->forgot_password1_link == 1 && $user->forgot_password1_submit == 1) {
            return redirect()->route('link.expired');
        }


        return view('auth.reset-password', ['userId' => $userId]);
    }

    public function updatePassword(Request $request)
    {
        $user = User::find($request->id);

        $user->pword = $request->password;
        $user->forgot_password1_link = 1;
        $user->forgot_password1_submit = 1;

        $user->save();

        return redirect('/')->with('success', 'Password updated successfully');
    }

    public function expiredLink()
    {
        return view('auth.link-expired');
    }
}
