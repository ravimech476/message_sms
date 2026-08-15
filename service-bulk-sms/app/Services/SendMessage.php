<?php

namespace App\Services;

use App\Exceptions\UnknownMessageProvider;
use App\Mail\FallbackEmail;
use App\Models\Message;
use App\Models\MessageUpdate;
use App\Models\Practice;
use App\Services\Smpp\SmppService;
use Exception;
use Illuminate\Support\Facades\DB;
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
            // From now on Vonage sends go through the SMPP pipeline (smsg_log),
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
     *   async=true  → create smsg_log row + publish to RabbitMQ sms.outbound; the
     *                 smpp:consume binder sends it (production parity).
     *   async=false → open an SMPP bind and send inline.
     *
     * Note: per-practice Vonage REST credentials are NOT used here — every practice
     * sends through the one shared SMPP account (SMPP_SYSTEM_ID), same as sms_expert.
     */
    protected function sendViaSmpp($data)
    {
        if (config('messages.smpp_async', true)) {
            $this->sendViaSmppQueue($data);
            return;
        }

        $this->sendViaSmppDirect($data);
    }

    /** Create the smsg_log row and enqueue a plain pending row for the pipeline. */
    protected function createSmsgLogRow($to, $from, $message): int
    {
        $now = now()->format('YmdHis');

        return DB::table('smsg_log')->insertGetId([
            'bigid'          => '',
            'mobnum'         => $to,
            'text'           => urlencode($message),
            'sentstatustext' => '',
            'originator'     => $from,
            'numparts'       => 1,
            'timesubmitted'  => $now,
            'dosendtime'     => $now,
            'sentstatus'     => 'pending',
            'initiator'      => 'SMPP',
            'suppliername'   => 'Vonage SMPP',
            'dayofyear'      => now()->format('Ymd'),
        ]);
    }

    /** Production path: publish to RabbitMQ; smpp:consume binder does the actual send. */
    protected function sendViaSmppQueue($data)
    {
        $to      = $data['to'];
        $message = $data['message'];
        $from    = $this->provider->sender_identifier ?: $this->practice->practice_name;

        $smsgLogId = $this->createSmsgLogRow($to, $from, $message);

        $rabbit = app(\App\Services\Queue\RabbitMQService::class);
        $ok = $rabbit->publishToQueue(config('rabbitmq.queues.outbound', 'sms.outbound'), [
            'smsg_log_id' => $smsgLogId,
            'to'          => $to,
            'from'        => $from,
            'message'     => $message,
        ]);
        $rabbit->close();

        if (!$ok) {
            DB::table('smsg_log')->where('id', $smsgLogId)->update([
                'sentstatus'     => 'fail',
                'sentstatustext' => 'Failed to publish to sms.outbound queue',
            ]);
            throw new Exception('Failed to publish SMS to the SMPP queue');
        }

        // Accepted for delivery; the binder + DLR flow finish it in smsg_log / SMS Log.
        $this->updateMessage('sent', "Queued to SMPP pipeline (smsg_log #{$smsgLogId})");
    }

    /** Inline path: open an SMPP bind and send now (no RabbitMQ, no binder needed). */
    protected function sendViaSmppDirect($data)
    {
        $to      = $data['to'];
        $message = $data['message'];
        $from    = $this->provider->sender_identifier ?: $this->practice->practice_name;

        $smsgLogId = $this->createSmsgLogRow($to, $from, $message);

        $smpp = new SmppService();
        try {
            $smpp->connect();
            $messageId = $smpp->sendSms($to, $message, $from);
            $smpp->close();
        } catch (Exception $e) {
            $smpp->close();
            DB::table('smsg_log')->where('id', $smsgLogId)->update([
                'sentstatus'     => 'fail',
                'sentstatustext' => substr($e->getMessage(), 0, 250),
            ]);
            throw $e; // outer catch records the MessageUpdate 'failed' + email fallback
        }

        DB::table('smsg_log')->where('id', $smsgLogId)->update([
            'sentstatus'              => 'ok',
            'timesent'                => now()->format('YmdHis'),
            'deliveryreceipt1'        => $messageId,
            'onesixty_suppliermsgref' => $messageId,
        ]);

        $this->updateMessage('sent', "SMPP message_id: {$messageId} (smsg_log #{$smsgLogId})");
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
