<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmppChecksReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $reportData;

    public function __construct(array $reportData)
    {
        $this->reportData = $reportData;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reportData['subject'] ?? 'SMS Expert: SMPP Regular Checks Report',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smpp-checks-report',
            with: ['reportData' => $this->reportData],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
