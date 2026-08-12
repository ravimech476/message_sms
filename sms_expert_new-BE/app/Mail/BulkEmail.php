<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BulkEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectLine;
    public $messageContent;

    public function __construct($subjectLine, $messageContent)
    {
        $this->subjectLine = $subjectLine;
        $this->messageContent = $messageContent;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('admin.emails.bulk')
            ->with([
                'messageContent' => $this->messageContent
            ]);
    }
}
