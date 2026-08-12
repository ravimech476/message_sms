<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RouteFixNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $routeData;

    public function __construct(array $routeData)
    {
        $this->routeData = $routeData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->routeData['subject'] ?? 'SMS Expert: Route auto-corrected - ' . now()->format('H:i:s jS M'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.route-fix-notification',
            with: ['routeData' => $this->routeData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
