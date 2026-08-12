<?php

namespace App\Http\Controllers;

use App\Services\Queue\SmsQueueService;
use App\Services\SMPP\SMPPPoolManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmppQueueController extends Controller
{
    private $smsQueueService;
    private $smppPoolManager;

    public function __construct()
    {
        $this->smsQueueService = new SmsQueueService();
        $this->smppPoolManager = new SMPPPoolManager();
    }

    /**
     * Queue SMS for sending via SMPP
     */
    public function sendSms(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required',
            'message' => 'required|max:1600',
            'from' => 'nullable|max:11',
            'priority' => 'nullable|integer|min:1|max:10',
            'scheduled_at' => 'nullable|date|after:now'
        ]);

        // Queue the SMS
        $result = $this->smsQueueService->queueSms([
            'mobile_number' => $validated['to'],
            'message' => $validated['message'],
            'sender_id' => $validated['from'] ?? env('SMPP_DEFAULT_SENDER', 'MYBRANDNAME'),
            'priority' => $validated['priority'] ?? 5,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'user_ref' => auth()->id() ?? 'api'
        ]);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'SMS queued successfully',
                'queue_id' => $result['queue_id']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to queue SMS',
            'error' => $result['error'] ?? 'Unknown error'
        ], 500);
    }
}
