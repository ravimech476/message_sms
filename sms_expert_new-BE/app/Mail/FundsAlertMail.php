<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FundsAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $alertData;

    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    public function envelope(): Envelope
    {
        $alertType = $this->alertData['alert_type'] ?? 'FundsAlert';

        return new Envelope(
            subject: $this->alertData['subject'] ?? "SMS Expert {$alertType} Alert",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.funds-alert',
            with: ['alertData' => $this->alertData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
