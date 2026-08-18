<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $referenceNo;
    public $email;
    public $phone;
    public $loginUrl;

    /**
     * Create a new message instance.
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
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Application Submitted Successfully - Qelvuno Recruitment',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.application_confirmation',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}