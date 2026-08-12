<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mailableClass;
    protected $recipient;
    protected $data;
    protected $ccRecipients;

    /**
     * Create a new job instance.
     *
     * @param string $mailableClass The fully qualified class name of the mailable
     * @param string|array $recipient The recipient email address
     * @param array $data The data to pass to the mailable
     * @param array $ccRecipients Optional CC recipients
     */
    public function __construct(string $mailableClass, $recipient, array $data = [], array $ccRecipients = [])
    {
        $this->mailableClass = $mailableClass;
        $this->recipient = $recipient;
        $this->data = $data;
        $this->ccRecipients = $ccRecipients;
        
        // Set the queue using the trait's method
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Create the mailable instance
            $mailable = new $this->mailableClass($this->data);
            
            // Build the mail
            $mail = Mail::to($this->recipient);
            
            // Add CC recipients if any
            if (!empty($this->ccRecipients)) {
                $mail->cc($this->ccRecipients);
            }
            
            // Send the email
            $mail->send($mailable);
            
            Log::info('Email sent successfully via queue', [
                'mailable' => $this->mailableClass,
                'recipient' => is_array($this->recipient) ? $this->recipient[0] : $this->recipient,
                'queue' => 'emails'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to send email via queue', [
                'mailable' => $this->mailableClass,
                'recipient' => is_array($this->recipient) ? $this->recipient[0] : $this->recipient,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Rethrow to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * The job failed to process.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception)
    {
        Log::error('Email job permanently failed', [
            'mailable' => $this->mailableClass,
            'recipient' => is_array($this->recipient) ? $this->recipient[0] : $this->recipient,
            'error' => $exception->getMessage()
        ]);
    }
}
