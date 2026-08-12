<?php

namespace App\Services\MessageProviders;

use Exception;
use GuzzleHttp\Client;

class BtMessageProvider extends MessageProvider
{
    /**
     * @inheritDoc
     */
    public function client(): BtMessageProvider
    {
        $this->client = new Client([
            'base_uri' => 'https://tailored.bt.com/cgphttp/servlet/',
            'auth' => [
                $this->credentials['username'],
                $this->credentials['password']
            ]
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function send($to, $from, $message): string
    {
        $request = $this->client->post('sendmsg', [
                'form_params' => [
                    'destination' => $this->to($to),
                    'text' => $message,
                ],
            ]);

        $response = $this->parseResponse($request);

        if (!$response->success) {
            throw new Exception($response->code . ' - '. $response->message);
        }

        return $response->message;
    }

    /**
     * Parses BT response pattern
     *
     * @param $response
     * @return object
     */
    protected function parseResponse($response)
    {
        $responseLine = $response->getBody()->getContents();

        return (object)[
            'success' => (int) substr($responseLine, 0, 1) === 0,
            'code' => substr($responseLine, 2, 3),
            'message' => trim(substr($responseLine, 6)),
        ];
    }

    /**
     * Replaces leading 0 to 44 in phone number
     *
     * @param string $phoneNumber
     * @return string|null
     */
    protected function to(string $phoneNumber): string
    {
        return preg_replace('/^0/', '44', $phoneNumber);
    }
}
