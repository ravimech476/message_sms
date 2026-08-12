<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminUserCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adminUser;
    public $password;
    public $loginUrl;
    public $roleName;

    /**
     * Create a new message instance.
     * 
     * @param array $data Contains adminUser data, password, and roleName
     */
    public function __construct($data)
    {
        // Handle both array format (from queue) and direct instantiation
        if (is_array($data)) {
            $this->adminUser = (object) ($data['admin_user'] ?? $data);
            $this->password = $data['password'] ?? '';
            $this->roleName = $data['role_name'] ?? $data['role'] ?? 'Admin';
            $this->loginUrl = $data['login_url'] ?? env('ADMIN_DOMAIN', config('app.url') . '/admin') . '/login';
        } else {
            // Direct instantiation with object
            $this->adminUser = $data;
            $this->password = '';
            $this->roleName = 'Admin';
            $this->loginUrl = env('ADMIN_DOMAIN', config('app.url') . '/admin') . '/login';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your SMS Expert Admin Account Has Been Created',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-user-created',
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
