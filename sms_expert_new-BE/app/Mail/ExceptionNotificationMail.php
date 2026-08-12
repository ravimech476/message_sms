<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class ExceptionNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $exceptionData;

    /**
     * Create a new message instance.
     */
    public function __construct(array $exceptionData)
    {
        $this->exceptionData = $exceptionData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $appName = config('app.name', 'Application');
        $environment = app()->environment();
        $exceptionClass = class_basename($this->exceptionData['exception_class']);

        return new Envelope(
            from: new Address(
                config('mail.from.address'),
                config('mail.from.name')
            ),
            subject: "🚨 [{$environment}] {$appName} - {$exceptionClass}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.exception-notification',
            with: [
                'exceptionData' => $this->exceptionData,
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
