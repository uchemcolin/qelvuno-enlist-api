<?php

namespace App\Jobs;

use App\Mail\PasswordChangedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordChangedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $user;
    public $ipAddress;
    public $userAgent;
    public $tries = 3;
    public $backoff = 10;

    public function __construct($user, $ipAddress = null, $userAgent = null)
    {
        $this->user = $user;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->queue(
            new PasswordChangedMail($this->user, $this->ipAddress, $this->userAgent)
        );
    }
}