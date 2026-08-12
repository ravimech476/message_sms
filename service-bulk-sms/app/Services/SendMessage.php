<?php

namespace App\Services;

use App\Exceptions\UnknownMessageProvider;
use App\Mail\FallbackEmail;
use App\Models\Message;
use App\Models\MessageUpdate;
use App\Models\Practice;
use Exception;
use Illuminate\Support\Facades\Mail;

class SendMessage
{
    /**
     * @var Practice
     */
    protected $practice;

    /**
     * @var Message
     */
    protected $message;

    /**
     * @var mixed
     */
    protected $provider;

    /**
     * SendMessage constructor.
     * @param Practice $practice
     * @param Message $message
     */
    public function __construct(Practice $practice, Message $message)
    {
        $this->practice = $practice;
        $this->message = $message;
        $this->provider = $practice->domain->defaultProvider;
    }

    /**
     * Sends message through a message provider.
     *
     * @param $data
     */
    public function send($data)
    {
        try {
            $messageProvider = $this->getDriver();

            if (!$messageProvider || !class_exists($messageProvider)) {
                throw new UnknownMessageProvider();
            }

            $senderIdentifier = $this->provider->sender_identifier ? $this->provider->sender_identifier : $this->practice->practice_name;
            $credentials = $this->provider->credentials->pluck('value', 'key')->toArray();
            $client = new $messageProvider($credentials, $this->provider->provider['required_credentials']);
            $response = $client->client()->send($data['to'], $senderIdentifier, $data['message']);

            $this->updateMessage('sent', $response);
        } catch (Exception $e) {
            $this->updateMessage('failed', $e->getMessage());

            $this->fallbackToEmail($data);
        }
    }

    /**
     * Fallbacks to email sending.
     *
     * @param null $data
     */
    public function fallbackToEmail($data = null)
    {
        $validateData = array_diff_key(array_flip(['email', 'body', 'subject']), $data['fallback']);

        if (is_array($data) && count($validateData) === 0) {
            Mail::to($data['fallback']['email'])
                ->send(new FallbackEmail($data['fallback']));

            $this->updateMessage('sent', null, 'email');
        }
    }

    /**
     * Updates message in the database.
     *
     * @param string $status
     * @param null $statusNote
     * @param string $messageType
     * @return mixed
     */
    protected function updateMessage(string $status, $statusNote = null, $messageType = 'sms')
    {
        $update = new MessageUpdate([
            'delivery_type' => $messageType,
            'status' => $status,
            'status_note' => $statusNote,
        ]);

        return $this->message->updates()->save($update);
    }

    /**
     * Returns back with the message provider class by the given driver.
     * @return string|null
     */
    protected function getDriver()
    {
        $driver = $this->provider->getOriginal('provider');

        return $driver ? '\\App\\Services\\MessageProviders\\'. ucfirst($driver) . 'MessageProvider' : null;
    }
}
