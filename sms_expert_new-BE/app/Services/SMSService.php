<?php

namespace App\Services;

use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS as VonageSMS;
use Illuminate\Support\Facades\Log;

class SMSService
{
    protected $vonageClient;

    public function __construct()
    {
        $vonage_key='9f78e690';
        $vonage_secret='NYxE1hbzAKY4Gqf0';
        // $basic  = new Basic(env('VONAGE_KEY'), env('VONAGE_SECRET'));
        $basic  = new Basic($vonage_key, $vonage_secret);
        $this->vonageClient = new Client($basic);
    }

    ### Message Send Functionlity
    public function sendSMS($to, $message)
    {
        try {
            Log::info('Sending SMS to ' . $to . ' with message: ' . $message);

            // Get the sender ID and set a default if it's null or empty
            // $from = env('VONAGE_SMS_FROM', 'DefaultSenderID');
            // if (empty($from)) {
            //     $from = 'DefaultSenderID'; 
            // }
            $from = 'DefaultSenderID'; 

            // Send SMS without additional parameters in the constructor
            $sms = new VonageSMS($to, $from , $message);

            // Send the SMS using the client
            $response = $this->vonageClient->sms()->send($sms);

            foreach ($response as $message) {
                Log::info('SMS sent with messageId: ' . $message->getMessageId());
                $status = $message->getStatus();
                if ($status != 0) {
                    Log::error('SMS failed with status: ' . $status);
                    return ['error' => 'Message failed with status: ' . $status];
                }
            }

            return ['success' => 'The message was sent successfully',
            'messageId' => $message->getMessageId()];
        } catch (\Exception $e) {
            Log::error('SMS sending error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
