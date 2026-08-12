<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProfileConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $formData;
    public $toAddress;
    public $ccAddress;
    public $confirmationToken;

    /**
     * Create a new message instance.
     */
    public function __construct($toAddress, $ccAddress = null, array $formData, $confirmationToken)
    {
        $this->toAddress = $toAddress;
        $this->ccAddress = $ccAddress;
        $this->formData = $formData;
        $this->confirmationToken = $confirmationToken;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->toAddress],
            cc: $this->ccAddress ? [$this->ccAddress] : [],
            subject: 'Your SMS Expert account has been changed - please confirm',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $confirmationUrl = route('profile.confirm', ['token' => $this->confirmationToken]);

        return new Content(
            view: 'emails.profile_confirmation',
            with: [
                'formData' => $this->formData,
                'confirmationUrl' => $confirmationUrl, // Pass the URL to the view
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

