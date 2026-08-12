<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmppProxyAuthErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $errorData;

    public function __construct(array $errorData)
    {
        $this->errorData = $errorData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->errorData['subject'] ?? 'SMS Expert: SMPP Proxy Authentication Error',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smpp-proxy-auth-error',
            with: ['errorData' => $this->errorData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
