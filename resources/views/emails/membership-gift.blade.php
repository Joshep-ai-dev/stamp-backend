<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kroo+ gift</title></head>
<body style="margin:0;background:#f1f4f3;color:#173a32;font:15px/1.55 Arial,Helvetica,sans-serif;">
<div style="display:none;max-height:0;overflow:hidden;">{{ $isBuyer ? 'Your Kroo+ gift is ready to make someone’s day.' : 'You’ve been gifted one year of Kroo+!' }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #dce5e1;border-radius:12px;overflow:hidden;">
<tr><td align="center" style="padding:17px;color:#042219;font-size:36px;font-weight:bold;letter-spacing:-2px;">kroo</td></tr>
<tr><td style="padding:32px 28px;background:#034a43;background-image:linear-gradient(110deg,#034a43,#0d8274,#82b7a5);color:#ffffff;">
<h1 style="font-size:32px;line-height:1.1;margin:0 0 12px;">{{ $isBuyer ? 'A gift that goes places.' : 'Your next adventure starts here.' }}</h1>
<p style="margin:0;">{{ $isBuyer ? 'You’ve gifted someone a world of possibilities with Kroo+.' : 'You’ve been gifted Kroo+!' }}</p>
</td></tr>
<tr><td style="padding:26px 28px;">
@if ($isBuyer)
<h2 style="text-align:center;font-size:23px;color:#042219;margin:0 0 20px;">Gift purchase confirmed!</h2>
<p>Hi {{ $sender }},</p>
<p>Thanks for gifting Kroo+! You’ve given someone a little more freedom to explore, travel, and make unforgettable memories.</p>
@else
<p>Hi there,</p>
<p><strong>{{ $sender }} just gifted you Kroo+!</strong><br>This means more ways to explore, travel, and experience the world.</p>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e8f5ef;border-radius:9px;margin:20px 0;"><tr><td style="padding:18px;">
<h3 style="margin:0 0 10px;color:#042219;font-size:17px;">Your Kroo+ gift</h3>
<p style="margin:6px 0;"><strong>{{ $isBuyer ? 'Recipient:' : 'Gift from:' }}</strong> {{ $isBuyer ? $gift->recipient_email : $sender }}</p>
<p style="margin:6px 0;"><strong>Gift:</strong> Kroo+ 1 Year</p>
<p style="margin:6px 0;overflow-wrap:anywhere;word-break:break-all;"><strong>Gift reference:</strong> {{ $gift->id }}</p>
@if ($isBuyer)<p style="margin:6px 0;"><strong>Purchase date:</strong> {{ $gift->paid_at->format('M j, Y') }}</p>@endif
</td></tr></table>
@if ($isBuyer)
<p style="background:#f0f8f4;padding:15px;border-radius:8px;color:#176248;">We’ve sent your Kroo+ gift to <strong>{{ $gift->recipient_email }}</strong>, so they can discover all the perks waiting for them.</p>
<p style="text-align:center;">Thanks for spreading the joy of travel with Kroo.</p>
<p style="text-align:center;">Happy travels,<br><strong>The Kroo Team</strong></p>
@else
@if (filled($gift->message))
<p><strong>A message from {{ $sender }}:</strong></p>
<p style="background:#f0f8f4;padding:15px;border-radius:8px;">{!! nl2br(e($gift->message)) !!}</p>
@endif
<p style="text-align:center;"><strong>Your one-use gift code</strong></p>
<p style="padding:16px 10px;background:#005642;color:#ffffff;border-radius:9px;text-align:center;font: bold 18px/1.5 monospace;overflow-wrap:anywhere;word-break:break-all;">{{ $gift->encrypted_code }}</p>
<p><strong>New to Kroo?</strong> Enter your name and this gift code on the welcome page. Your account and one year of Kroo+ will activate together.</p>
<p><strong>Already a member?</strong> Sign into Kroo, open the + page, enter this code in the gift-code field, and tap Redeem gift.</p>
<p>Your year starts when you redeem the code. No payment or recurring subscription is needed. Keep this gift code private. It can only be redeemed once.</p>
<p>Welcome to Kroo+.<br>We can’t wait to see where you go!</p><p>The Kroo Team</p>
@endif
</td></tr>
<tr><td style="background:#042219;color:#dcece5;padding:18px 22px;text-align:center;"><strong style="font-size:24px;color:#ffffff;letter-spacing:-1px;">kroo</strong><br>Collect The World With Kroo.</td></tr>
<tr><td style="font-size:11px;color:#71847c;text-align:center;padding:16px;">You’re receiving this email because {{ $isBuyer ? 'you purchased a Kroo+ gift.' : 'someone gifted you Kroo+.' }}<br>&copy; {{ now()->year }} Kroo. All rights reserved.</td></tr>
</table></td></tr></table>
</body></html>
