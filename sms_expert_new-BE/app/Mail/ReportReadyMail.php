<?php

namespace App\Mail;

use App\Models\ReportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public ReportJob $job;
    public string $downloadUrl;

    public function __construct(ReportJob $job, string $downloadUrl)
    {
        $this->job = $job;
        $this->downloadUrl = $downloadUrl;
    }

    public function build()
    {
        return $this->subject('Your report is ready: ' . $this->job->report_name)
            ->view('emails.report-ready', [
                'job' => $this->job,
                'downloadUrl' => $this->downloadUrl,
            ]);
    }
}
