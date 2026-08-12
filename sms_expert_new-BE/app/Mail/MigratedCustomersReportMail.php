<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MigratedCustomersReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $stats;

    public function __construct(array $stats)
    {
        $this->stats = $stats;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SMS Expert — Migrated Customers Daily Report (' . ($this->stats['date'] ?? '') . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.migrated-customers-report',
            with: ['stats' => $this->stats],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
