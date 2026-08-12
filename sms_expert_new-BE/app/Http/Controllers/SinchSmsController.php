<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SinchSmppService;

class SinchSmsController extends Controller
{

    protected $sms;

    public function __construct(SinchSmppService $sms)
    {
        $this->sms = $sms;
    }

    public function send(Request $request)
    {

        $request->validate([
            'mobile' => 'required',
            'message' => 'required'
        ]);

        try {

            $messageId = $this->sms->sendSms(
                $request->mobile,
                $request->message
            );

            return response()->json([
                'status' => 'success',
                'message_id' => $messageId
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage()
            ]);

        }

    }

}