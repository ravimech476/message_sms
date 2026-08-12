<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Templated SMPP error alert. Replaces the plain Mail::raw used previously
 * so SMPP failure emails share the same SMS Expert branded layout as the
 * other notification mails (DeliveryReceiptFailureMail etc.).
 */
class SmppErrorAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    /**
     * $data keys:
     *   subject_line  string  short title (e.g. "Vonage SMPP bind failed")
     *   body          string  human-readable explanation
     *   context       array   key=>value pairs (host, port, system_id, error, ...)
     *   env           string  app environment (production / staging / local)
     *   host          string  server hostname
     *   throttled_for string  description of when next email may fire
     *   sent_at       string  formatted timestamp
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        $env     = $this->data['env']  ?? 'unknown';
        $host    = $this->data['host'] ?? 'unknown';
        $subject = $this->data['subject_line'] ?? 'SMPP error';

        return new Envelope(
            subject: "[SMPP {$env}@{$host}] {$subject}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.smpp-error-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
