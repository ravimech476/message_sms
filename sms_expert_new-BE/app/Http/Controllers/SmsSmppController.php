<?php

// namespace App\Http\Controllers;

// use App\Services\SmppService;
// use Illuminate\Http\Request;

// class SmsSmppController extends Controller
// {
//     protected $smppService;

//     public function __construct(SmppService $smppService)
//     {
//         $this->smppService = $smppService;
//     }

//     public function sendSms(Request $request)
//     {
//         // Retrieve sender, recipient, and message from the request
//         $sender = $request->input('sender');
//         $receiver = $request->input('receiver');
//         $message = $request->input('message');

//         // Connect to SMPP server
//         $this->smppService->connect('f43f4fc3', '4iD2Yi1fS6O7t4wj');

//         // Send the SMS
//         $result = $this->smppService->sendSms($sender, $receiver, $message);

//         // Disconnect from the server
//         $this->smppService->disconnect();

//         // Return the result
//         return response()->json(['result' => $result]);
//     }
// }


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Franzose\LaravelSmpp\Facades\LaravelSmpp;

class SmsSmppController extends Controller
{
    public function sendSms(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'txtTo' => 'required|string|max:15', // Adjust max length as necessary
            'messageContent' => 'required|string|max:160', // Adjust based on SMS length
        ]);

        $to = $request->input('txtTo');
        $message = $request->input('messageContent');

        // Send SMS using Laravel Smpp
        try {
            $response = LaravelSmpp::send($to, $message);

            if ($response->isSuccessful()) {
                return response()->json(['status' => 'success', 'message' => 'SMS sent successfully.']);
            }

            return response()->json(['status' => 'error', 'message' => 'Failed to send SMS.'], 500);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}

