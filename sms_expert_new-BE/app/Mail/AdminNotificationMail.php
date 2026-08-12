<?php

namespace App\Mail;

use App\Models\NotificationRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $title;
    public $notificationMessage;
    public $type;
    public $userName;
    public $notificationId;
    public $recipientId;
    public $userId;
    public $requiresAcknowledgment;

    /**
     * Create a new message instance.
     * 
     * @param array $data Contains notification data
     */
    public function __construct($data)
    {
        // Handle array format from queue
        if (is_array($data)) {
            $this->title = $data['title'] ?? 'Notification';
            $this->notificationMessage = $data['message'] ?? '';
            $this->type = $data['notification_type'] ?? 'info';
            $this->userName = $data['user_name'] ?? 'Customer';
            $this->notificationId = $data['notification_id'] ?? null;
            $this->recipientId = $data['recipient_id'] ?? null;
            $this->userId = $data['user_id'] ?? null;
            $this->requiresAcknowledgment = $data['requires_acknowledgment'] ?? false;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SMS Expert: ' . $this->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'title' => $this->title,
                'notificationMessage' => $this->notificationMessage,
                'type' => $this->type,
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

    /**
     * Actions to perform after the message has been sent.
     */
    public function sent($message)
    {
        // Update recipient record to mark email as sent
        if ($this->recipientId) {
            try {
                NotificationRecipient::where('id', $this->recipientId)->update([
                    'email_sent' => true,
                    'email_sent_at' => now(),
                ]);
                
                Log::info('Notification email sent and recipient updated', [
                    'notification_id' => $this->notificationId,
                    'recipient_id' => $this->recipientId,
                    'user_id' => $this->userId,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update recipient after email sent', [
                    'recipient_id' => $this->recipientId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
