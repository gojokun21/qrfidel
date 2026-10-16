<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Linkul de conectare al clientului (ADR 009). Duce la o pagină de confirmare, nu loghează direct.
 */
class CustomerMagicLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $url,
        public ?Business $business,
        public int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->business !== null
                ? "Linkul tău de conectare la {$this->business->name}"
                : 'Linkul tău de conectare la '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer-magic-link');
    }
}
