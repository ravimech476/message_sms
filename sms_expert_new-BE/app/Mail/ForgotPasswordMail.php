<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $toAddress;
    public $ccAddress;
    public $userName;
    public $userId;

    /**
     * Create a new message instance.
     */
    public function __construct($toAddress, $ccAddress = null,$userName,$userId)
    {
        $this->toAddress = $toAddress;
        $this->ccAddress = $ccAddress;
        $this->userName =  $userName;
        $this->userId =  $userId;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->toAddress],
            cc: $this->ccAddress ? [$this->ccAddress] : [],
            subject: 'SMS Expert Password Reset - ' . $this->userName ,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.forgot_password',
            with: [
                'userId' =>  $this->userId,
                'userName' => $this->userName,
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
