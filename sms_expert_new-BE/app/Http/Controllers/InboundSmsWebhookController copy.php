<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InboundSmsWebhookController extends Controller
{
    /**
     * Handle inbound SMS webhook from Nexmo
     */
    public function handle(Request $request)
    {
        try {
            Log::info('Inbound SMS Webhook Received', $request->all());

            // Nexmo sends data as query parameters or POST data
            $msisdn = $request->input('msisdn'); // Sender number
            $to = $request->input('to'); // Destination number (virtual number)
            $text = $request->input('text'); // Message text
            $messageId = $request->input('messageId'); // Nexmo message ID
            $type = $request->input('type'); // Message type (text, binary, etc)
            $keyword = $request->input('keyword', ''); // First word of message
            $messageTimestamp = $request->input('message-timestamp'); // When message was sent

            // Validate required fields
            if (!$msisdn || !$to || !$text) {
                Log::warning('Inbound SMS missing required fields', $request->all());
                return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
            }

            // Parse keyword and subkeyword from message
            $messageParts = explode(' ', trim($text), 2);
            $keyword = strtoupper($messageParts[0] ?? '');
            $subkeyword = isset($messageParts[1]) ? strtoupper(explode(' ', $messageParts[1])[0] ?? '') : null;

            // Insert into itagg_incominglog table
            DB::table('itagg_incominglog')->insert([
                'recieved' => now(),
                'source' => $msisdn,
                'dest' => $to,
                'keyword' => $keyword,
                'subkeyword' => $subkeyword,
                'msg' => $text,
                'network' => 0, // Set based on your logic
                'matched' => 1, // Set based on keyword matching logic
                'user_bigid' => null, // Set based on your user matching logic
                'mobile_client_bigid' => null,
                'mobile_client_type' => null,
                'mobile_client_version' => null,
                'viewed_by_java_desktop' => 0,
                'operator_message_id' => $messageId,
                'msisdnAlias' => '',
                'mbloxDeliverer' => null,
            ]);

            Log::info('Inbound SMS saved successfully', [
                'from' => $msisdn,
                'to' => $to,
                'keyword' => $keyword,
                'message_id' => $messageId
            ]);

            // Return 200 OK to Nexmo
            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error('Error processing inbound SMS webhook: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Test endpoint to verify webhook is working
     */
    public function test()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook endpoint is working',
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}
