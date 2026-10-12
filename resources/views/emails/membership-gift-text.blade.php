@if ($isBuyer)
Gift purchase confirmed!

Hi {{ $sender }},
Thanks for gifting Kroo+! You’ve given someone a little more freedom to explore, travel, and make unforgettable memories.
Recipient: {{ $gift->recipient_email }}
Gift: Kroo+ 1 Year
Purchase date: {{ $gift->paid_at->format('M j, Y') }}
We’ve sent your Kroo+ gift to {{ $gift->recipient_email }}.

Thanks for spreading the joy of travel with Kroo.
@else
Hi there,
{{ $sender }} just gifted you Kroo+!
Gift: Kroo+ 1 Year
@if (filled($gift->message))

A message from {{ $sender }}:
{{ $gift->message }}
@endif

Gift code (one-time use): {{ $gift->encrypted_code }}

New to Kroo? Enter your name and this gift code on the welcome page. Your account and one year of Kroo+ will activate together.
Already a member? Sign into Kroo, open the + page, enter this code in the gift-code field, and tap Redeem gift.
Your year starts when you redeem the code. No payment or recurring subscription is needed.
Keep this gift code private. It can only be redeemed once.

Welcome to Kroo+. We can’t wait to see where you go!
@endif

The Kroo Team
Collect The World With Kroo.
Copyright {{ now()->year }} Kroo. All rights reserved.
