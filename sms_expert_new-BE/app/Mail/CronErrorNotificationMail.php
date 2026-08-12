<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class CronErrorNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $cronData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $cronData)
    {
        $this->cronData = $cronData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Application');
        $environment = app()->environment();

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            subject: "⚠️ [{$environment}] {$appName} - Cron Job Failed: {$this->cronData['command']}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.cron-error-notification',
            with: [
                'cronData' => $this->cronData,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
