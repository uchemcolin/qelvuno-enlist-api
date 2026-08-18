<?php

namespace App\Jobs;

use App\Mail\PasswordResetMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $email;
    public $token;
    public $resetUrl;
    public $name;

    public $tries = 3;
    public $backoff = 10;

    public function __construct($email, $token, $resetUrl, $name = null)
    {
        $this->email = $email;
        $this->token = $token;
        $this->resetUrl = $resetUrl;
        $this->name = $name;
    }

    public function handle(): void
    {
        /*Mail::to($this->email)->send(new PasswordResetMail(
            $this->token,
            $this->resetUrl,
            $this->name
        ));*/

        Mail::to($this->email)->queue(
            new PasswordResetMail(
                $this->token,
                $this->resetUrl,
                $this->name
            )
        );
    }
}