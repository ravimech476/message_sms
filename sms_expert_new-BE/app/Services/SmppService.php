<?php

namespace App\Services;

use Mironoff\Smpp\Client;
use Mironoff\Smpp\Transport;
use Mironoff\Smpp\Sms;

class SmppService
{
    protected $smppClient;
    protected $transport;

    public function __construct()
    {
        // SMPP server configurations
        $host = 'smpp1.nexmo.com'; // SMPP server
        $port = 8000;                   // SMPP port (default is 2775)

        // Transport setup (TCP/IP communication)
        $this->transport = new Transport($host, $port);

        // Create an SMPP client instance
        $this->smppClient = new Client($this->transport);
        
        // Optional: Set debug mode to see detailed logs
        $this->smppClient->setDebug(true);

        // Connection timeout (in seconds)
        $this->smppClient->setTimeout(30);
    }

    // Connect and bind with SMPP credentials
    public function connect($username, $password)
    {
        $this->smppClient->bindTransmitter($username, $password);
    }

    // Send an SMS
    public function sendSms($sender, $receiver, $message)
    {
        // Create an SMS instance
        $sms = new Sms();
        $sms->sender = $sender;
        $sms->recipient = $receiver;
        $sms->message = $message;

        // Send the SMS
        return $this->smppClient->send($sms);
    }

    // Close the connection after sending the message
    public function disconnect()
    {
        $this->smppClient->close();
    }
}
