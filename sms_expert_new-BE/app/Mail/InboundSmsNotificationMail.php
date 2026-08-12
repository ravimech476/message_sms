<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InboundSmsNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $smsData;

    public function __construct(array $smsData)
    {
        $this->smsData = $smsData;
    }

    public function envelope(): Envelope
    {
        $keyword = $this->smsData['keyword'] ?? 'SMS';
        $source = $this->smsData['source'] ?? 'Unknown';

        return new Envelope(
            subject: $this->smsData['subject'] ?? "Someone has texted your SMS Keyword {$keyword} - from {$source}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inbound-sms-notification',
            with: ['smsData' => $this->smsData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
