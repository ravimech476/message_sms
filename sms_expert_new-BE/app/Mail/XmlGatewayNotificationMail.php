<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class XmlGatewayNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $notificationData;

    public function __construct(array $notificationData)
    {
        $this->notificationData = $notificationData;
    }

    public function envelope(): Envelope
    {
        $type = $this->notificationData['type'] ?? 'notification';
        $defaultSubject = $type === 'error'
            ? 'XML-SMS Gateway Warning/Error'
            : 'XML-SMS Gateway Confirmation - OK';

        return new Envelope(
            subject: $this->notificationData['subject'] ?? $defaultSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.xml-gateway-notification',
            with: ['notificationData' => $this->notificationData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
