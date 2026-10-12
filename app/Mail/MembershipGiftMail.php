<?php

namespace App\Mail;

use App\Models\MembershipGift;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class MembershipGiftMail extends Mailable
{
    public string $recipientName;

    public function __construct(public MembershipGift $gift, public string $sender, public bool $isBuyer = false)
    {
        $names = User::whereRaw('lower(email) = ?', [strtolower(trim($gift->recipient_email))])
            ->pluck('name')->filter(fn ($name) => filled($name))->unique()->values();
        $this->recipientName = $names->count() === 1 ? $names->first() : 'your friend';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isBuyer ? 'Your Kroo+ gift purchase is confirmed!' : 'You received a year of Kroo+!');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.membership-gift', text: 'emails.membership-gift-text');
    }
}
