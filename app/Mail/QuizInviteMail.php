<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuizInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invite;
    public $quiz;

    public function __construct($invite, $quiz)
    {
        $this->invite = $invite;
        $this->quiz   = $quiz;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to take a Quiz — ' . $this->quiz->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quiz-invite',
        );
    }
}
