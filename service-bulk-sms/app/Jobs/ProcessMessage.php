<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Practice;
use App\Services\SendMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Practice
     */
    protected $practice;

    /**
     * @var Message
     */
    private $message;

    /**
     * @var array
     */
    protected $data;

    /**
     * Create a new job instance.
     *
     * @param Practice $practice
     * @param Message $message
     * @param array $data
     */
    public function __construct(Practice $practice, Message $message, array $data)
    {
        $this->practice = $practice;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $sendMessage = new SendMessage($this->practice, $this->message);
        $sendMessage->send($this->data);
    }
}
