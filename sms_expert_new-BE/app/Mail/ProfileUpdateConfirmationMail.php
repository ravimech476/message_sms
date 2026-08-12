<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ProfileUpdateConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public \stdClass $user;
    public \stdClass $newProfile;
    public string $confirmationUrl;

    public function __construct(\stdClass $user, \stdClass $newProfile, string $confirmationUrl)
    {
        $this->user = $user;
        $this->newProfile = $newProfile;
        $this->confirmationUrl = $confirmationUrl;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->user->contactemail],
            subject: 'Your SMS Expert account has been changed - please confirm',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile_update_confirmation',
            with: [
                'user' => $this->user,
                'newProfile' => $this->newProfile,
                'confirmationUrl' => $this->confirmationUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
