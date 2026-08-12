<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminSendLogin extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $subjectLine;
    public $ccEmail;

    public function __construct($user,$ccEmail)
    {
        $this->user = $user;
        $this->subjectLine = 'SMS Expert Login Request';
        $this->ccEmail = $ccEmail;

    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            cc: [$this->ccEmail],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'admin.emails.admin_send_login',
            with: [
                'user' => $this->user,
                'subjectLine' => $this->subjectLine,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
