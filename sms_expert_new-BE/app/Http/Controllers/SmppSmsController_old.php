<?php
namespace App\Http\Controllers;

use App\Services\SMPPService;
use Illuminate\Http\Request;

class SmppSmsController extends Controller
{
    protected $smppService;

    public function __construct(SMPPService $smppService)
    {
        $this->smppService = $smppService;
    }

    public function send(Request $request)
    {
        $request->validate([
            'to' => 'required',
            'message' => 'required',
        ]);

        $result = $this->smppService->sendSMS($request->to, $request->message);
        $this->smppService->disconnect();

        return $result
            ? response()->json(['message' => 'SMS sent successfully.'])
            : response()->json(['message' => 'Failed to send SMS.'], 500);
    }
}
