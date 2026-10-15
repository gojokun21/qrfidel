<?php

namespace App\Mail;

use App\Models\Business;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emailul cu linkul de acceptare. Tokenul în clar există doar aici (în DB stă hash-ul).
 */
class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public Business $business,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitație în echipa {$this->business->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-invitation',
            with: [
                'url' => route('invitations.show', $this->token),
                'role' => $this->invitation->role->label(),
                'expiresAt' => $this->invitation->expires_at->timezone($this->business->timezone)->format('d.m.Y H:i'),
            ],
        );
    }
}
