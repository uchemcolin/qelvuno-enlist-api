<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeAccountMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $phone;
    public $email;

    public function __construct($user)
    {
        //$this->name = $user->name ?? $user->phone;
        
        // Default to "Applicant" when the user has no name.
        // Use a generic fallback instead of displaying the user's phone number.
        $this->name = $user->name ?? 'Applicant';
        $this->phone = $user->phone;
        $this->email = $user->email;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Qelvuno Recruitment Portal - Account Activated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_account',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}