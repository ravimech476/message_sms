<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * API Error Notification Email
 * 
 * Sent via RabbitMQ when API errors occur
 */
class ApiErrorNotification extends Mailable
{
    use Queueable, SerializesModels;

    public array $errorData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $errorData)
    {
        $this->errorData = $errorData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $severity = strtoupper($this->errorData['severity'] ?? 'ERROR');
        $path = $this->errorData['request']['path'] ?? 'Unknown';
        $appName = config('app.name', 'SMS Expert');

        return new Envelope(
            subject: "[{$severity}] {$appName} - Mobile API Error: {$path}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.api-error-notification',
            with: [
                'errorData' => $this->errorData,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
