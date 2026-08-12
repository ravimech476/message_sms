<?php
namespace App\Services;

require_once public_path('smpp/smppclient.class.php'); // Update with the actual path to the smppclient class
require_once public_path('smpp/gsmencoder.class.php');

use SmppClient;
use SmppAddress;

class SMPPService
{
    protected $client;
    protected $transport;



    public function __construct()
    {
        $host = "smpp1.nexmo.com";
        $port = "8000";
        $systemId = "f43f4fc3";
        $password =  "4iD2Yi1fS6O7t4wj";

        // Setup transport layer (TCP/IP connection)
        $this->transport = new \SocketTransport([$host], $port);
        $this->transport->setRecvTimeout(10000); // Set timeout
        
        // Initialize SMPP client
        $this->client = new SmppClient($this->transport);

        // Open connection
        $this->transport->open();

        // Bind to the SMPP server
        $this->client->bindTransmitter($systemId, $password);
    }

    public function sendSMS($to, $from, $message)
    {
        // Encode message
        $encodedMessage = GsmEncoder::utf8_to_gsm0338($message);

        // Create a new SMPP address object for the sender and recipient
        $fromAddress = new SmppAddress($from, SMPP::TON_ALPHANUMERIC);
        $toAddress = new SmppAddress($to, SMPP::TON_INTERNATIONAL, SMPP::NPI_E164);

        // Send the SMS
        $this->client->sendSMS($fromAddress, $toAddress, $encodedMessage);

        // Unbind after sending
        $this->client->close();
    }
}