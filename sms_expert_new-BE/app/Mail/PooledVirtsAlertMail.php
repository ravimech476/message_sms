<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PooledVirtsAlertMail extends Mailable
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
            subject: $this->alertData['subject'] ?? 'SMS Expert: Pooled Virtual Numbers Alert',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pooled-virts-alert',
            with: ['alertData' => $this->alertData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
