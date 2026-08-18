<?php

namespace App\Jobs;

use App\Mail\ApplicationConfirmationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendApplicationConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $name;
    public $referenceNo;
    public $email;
    public $phone;
    public $loginUrl;

    public $tries = 3;  // Retry 3 times if failed
    public $backoff = 10;  // Wait 10 seconds between retries

    /**
     * Create a new job instance.
     */
    public function __construct($name, $referenceNo, $email, $phone, $loginUrl)
    {
        $this->name = $name;
        $this->referenceNo = $referenceNo;
        $this->email = $email;
        $this->phone = $phone;
        $this->loginUrl = $loginUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /*Mail::to($this->email, $this->name)
            ->send(new ApplicationConfirmationMail(
                $this->name,
                $this->referenceNo,
                $this->email,
                $this->phone,
                $this->loginUrl
            ));*/

        Mail::to($this->email, $this->name)
            ->queue(new ApplicationConfirmationMail(
                $this->name,
                $this->referenceNo,
                $this->email,
                $this->phone,
                $this->loginUrl
            ));
    }
}