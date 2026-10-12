<?php

namespace App\Mail;

use App\Models\MembershipGift;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MembershipGiftMail extends Mailable
{
    public function __construct(public MembershipGift $gift, public string $sender, public bool $isBuyer = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isBuyer ? 'Your Kroo+ gift purchase is confirmed!' : 'You received a year of Kroo+!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.membership-gift', text: 'emails.membership-gift-text');
    }
}
