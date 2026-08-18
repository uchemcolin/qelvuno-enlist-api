<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $changedAt;
    public $ipAddress;
    public $userAgent;

    public function __construct($user, $ipAddress = null, $userAgent = null)
    {
        //$this->name = $user->name ?? $user->phone;
        $this->name = $user->name ?? "Applicant";
        $this->changedAt = now()->format('F j, Y \a\t g:i A');
        $this->ipAddress = $ipAddress ?? request()->ip();
        $this->userAgent = $userAgent ?? request()->userAgent();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Security Alert: Your Password Was Changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_changed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}