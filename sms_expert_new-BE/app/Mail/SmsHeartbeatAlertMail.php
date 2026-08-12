<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmsHeartbeatAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $alertData;

    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->alertData['subject'] ?? 'SMS Expert: Heartbeat Alert',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.sms-heartbeat-alert',
            with: ['alertData' => $this->alertData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
