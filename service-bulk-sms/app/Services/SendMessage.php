<?php

namespace App\Services;

use App\Exceptions\UnknownMessageProvider;
use App\Mail\FallbackEmail;
use App\Models\Message;
use App\Models\MessageUpdate;
use App\Models\Practice;
use App\Services\Smpp\SmppService;
use Exception;
use Illuminate\Support\Facades\Mail;

/**
 * @author Anand Karthik (modified — routes Vonage sends through the SMPP pipeline)
 */
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
            // Vonage sends go through the SMPP pipeline (tracked on message_updates),
            // not the Vonage REST API. All other drivers keep the REST path.
            if ($this->usesSmpp()) {
                $this->sendViaSmpp($data);
                return;
            }

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

    /** True when this provider should send over SMPP instead of the REST API. */
    protected function usesSmpp(): bool
    {
        return strtolower((string) $this->provider->getOriginal('provider')) === 'vonage';
    }

    /**
     * Send via the SMPP pipeline. Two modes (config('messages.smpp_async')):
     *   async=true  → publish to RabbitMQ sms.outbound; the smpp:consume binder sends
     *                 it and stamps supplier_message_id (production parity).
     *   async=false → open an SMPP bind and send inline.
     *
     * Delivery lives on message_updates: created_at = sent time, supplier_message_id
     * matches the DLR, delivered_at + status are filled when the receipt arrives.
     * Per-practice REST credentials are NOT used — all sends go through the one
     * shared SMPP account (SMPP_SYSTEM_ID), same as sms_expert.
     */
    protected function sendViaSmpp($data)
    {
        if (config('messages.smpp_async', true)) {
            $this->sendViaSmppQueue($data);
            return;
        }

        $this->sendViaSmppDirect($data);
    }

    /** Production path: publish to RabbitMQ; smpp:consume binder does the actual send. */
    protected function sendViaSmppQueue($data)
    {
        $to      = $data['to'];
        $message = $data['message'];
        $from    = $this->provider->sender_identifier ?: $this->practice->practice_name;

        // Record the send now; the binder stamps supplier_message_id after it submits.
        $update = $this->updateMessage('sent', 'Queued to SMPP pipeline');

        $rabbit = app(\App\Services\Queue\RabbitMQService::class);
        $ok = $rabbit->publishToQueue(config('rabbitmq.queues.outbound', 'sms.outbound'), [
            'message_update_id' => $update->id,
            'to'                => $to,
            'from'              => $from,
            'message'           => $message,
        ]);
        $rabbit->close();

        if (!$ok) {
            $update->update(['status' => 'failed', 'status_note' => 'Failed to publish to sms.outbound queue']);
            throw new Exception('Failed to publish SMS to the SMPP queue');
        }
    }

    /** Inline path: open an SMPP bind and send now (no RabbitMQ, no binder needed). */
    protected function sendViaSmppDirect($data)
    {
        $to      = $data['to'];
        $message = $data['message'];
        $from    = $this->provider->sender_identifier ?: $this->practice->practice_name;

        $smpp = new SmppService();
        try {
            $smpp->connect();
            $messageId = $smpp->sendSms($to, $message, $from);
            $price = $smpp->getLastPrice();
            $smpp->close();
        } catch (Exception $e) {
            $smpp->close();
            throw $e; // outer catch records the MessageUpdate 'failed' + email fallback
        }

        $this->updateMessage('sent', "SMPP message_id: {$messageId}", 'sms', $messageId, $price);
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
    protected function updateMessage(string $status, $statusNote = null, $messageType = 'sms', $supplierMessageId = null, $costPerSms = null)
    {
        $update = new MessageUpdate([
            'delivery_type'       => $messageType,
            'status'              => $status,
            'status_note'         => $statusNote,
            'supplier_message_id' => $supplierMessageId,
            'cost_per_sms'        => $costPerSms,
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
