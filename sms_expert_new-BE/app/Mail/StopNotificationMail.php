<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StopNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $stopData;

    public function __construct(array $stopData)
    {
        $this->stopData = $stopData;
    }

    public function envelope(): Envelope
    {
        $commandType = $this->stopData['command_type'] ?? 'STOP';
        $dest = $this->stopData['destination'] ?? '';

        return new Envelope(
            subject: $this->stopData['subject'] ?? "iTAGG: {$commandType} to {$dest} - ACTION REQUIRED",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.stop-notification',
            with: ['stopData' => $this->stopData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
