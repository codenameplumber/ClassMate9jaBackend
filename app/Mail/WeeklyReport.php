<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $child,
        public array $summary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->child->firstName() . "'s week: "
                . $this->summary['days_studied'] . '/7 days studied, '
                . $this->summary['average'] . '% average',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.weekly-report');
    }
}
