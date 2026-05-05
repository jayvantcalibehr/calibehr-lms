<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InterviewInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invite;
    public $interview;

    public function __construct($invite, $interview)
    {
        $this->invite    = $invite;
        $this->interview = $interview;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited for an Interview — ' . $this->interview->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.interview-invite',
        );
    }
}
