<?php

namespace App\Jobs;

use App\Mail\WelcomeAccountMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendWelcomeAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $user;
    public $tries = 3;
    public $backoff = 10;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->queue(new WelcomeAccountMail($this->user));
    }
}