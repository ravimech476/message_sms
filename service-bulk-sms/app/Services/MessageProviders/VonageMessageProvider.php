<?php

namespace App\Services\MessageProviders;

use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;

class VonageMessageProvider extends MessageProvider
{
    /**
     * @inheritDoc
     */
    public function client(): VonageMessageProvider
    {
        $credentialSet = new Basic($this->credentials['api_key'], $this->credentials['api_secret']);
        $this->client = new Client($credentialSet);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function send($to, $from, $message): string
    {
        $text = new SMS($this->to($to), $from, $message);

        $response = $this->client->sms()->send($text);

        // @TODO: We might write a generic response interface instead of a string
        return 'Message ID: ' . $response->current()->getMessageId();
    }

    /**
     * Replaces leading 0 to 44 in phone number, because Vonage
     * only accepts phone numbers starting with 0.
     *
     * @param string $phoneNumber
     * @return string|null
     */
    public function to(string $phoneNumber): string
    {
        return preg_replace('/^0/', '44', $phoneNumber);
    }
}
