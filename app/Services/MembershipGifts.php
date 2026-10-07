<?php

namespace App\Services;

use App\Models\MembershipGift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class MembershipGifts
{
    public function transactions(User $buyer, string $product): array
    {
        abort_if(trim((string) config('services.revenuecat.secret_api_key')) === '', 503, 'Gift payment verification is not configured.');
        $response = Http::acceptJson()->withToken(config('services.revenuecat.secret_api_key'))
            ->timeout(15)->retry(2, 250)
            ->get('https://api.revenuecat.com/v1/subscribers/'.rawurlencode($buyer->id));
        if ($response->status() === 404) return [];
        return $response->throw()->json('subscriber.non_subscriptions')[$product] ?? [];
    }

    public function create(User $buyer, array $data): MembershipGift
    {
        $allowed = array_filter(explode(',', (string) config('services.revenuecat.gift_product_ids')));
        abort_unless(in_array($data['productId'], $allowed, true), 503, 'The prepaid gift product is not configured.');
        $transactions = $this->transactions($buyer, $data['productId']);
        return DB::transaction(function () use ($buyer, $data, $transactions) {
            User::whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $pending = MembershipGift::where('buyer_id', $buyer->id)->whereNull('paid_at')->whereNull('cancelled_at')->first();
            if ($pending) {
                abort_unless($pending->recipient_email === strtolower(trim($data['recipientEmail']))
                    && ($pending->message ?? '') === trim($data['message'] ?? ''), 409,
                    'A gift purchase is already pending for '.$pending->recipient_email.'. Please finish that gift first.');
                return $pending;
            }
            $gift = MembershipGift::create([
                'buyer_id' => $buyer->id, 'recipient_email' => strtolower(trim($data['recipientEmail'])),
                'message' => trim($data['message'] ?? ''), 'product_id' => $data['productId'],
                'baseline_transactions' => array_column($transactions, 'id'),
            ]);
            $gift->setAttribute('checkout_allowed', true);
            return $gift;
        });
    }

    public function verify(MembershipGift $gift): MembershipGift
    {
        if ($gift->paid_at || $gift->cancelled_at) return $gift;
        $transactions = $this->transactions(User::findOrFail($gift->buyer_id), $gift->product_id);
        return DB::transaction(function () use ($gift, $transactions) {
            // Serialize claims for this buyer, including concurrent retries and the scheduler.
            User::whereKey($gift->buyer_id)->lockForUpdate()->firstOrFail();
            $gift = MembershipGift::whereKey($gift->id)->lockForUpdate()->firstOrFail();
            if ($gift->paid_at || $gift->cancelled_at) return $gift;
            foreach ($transactions as $transaction) {
                $id = $transaction['id'] ?? null;
                if (!is_string($id) || in_array($id, $gift->baseline_transactions, true)
                    || !empty($transaction['refunded_at'])
                    || (($transaction['is_sandbox'] ?? true) && !config('services.revenuecat.gift_allow_sandbox'))
                    || !in_array($transaction['store'] ?? '', ['app_store', 'play_store'], true)
                    || empty($transaction['purchase_date'])
                    || CarbonImmutable::parse($transaction['purchase_date'])->lt($gift->created_at->copy()->subSeconds(5))
                    || MembershipGift::where('transaction_id', $id)->exists()) continue;
                $code = strtoupper(bin2hex(random_bytes(12)));
                $gift->forceFill([
                    'transaction_id' => $id, 'paid_at' => now(),
                    'code_hash' => hash('sha256', $code), 'encrypted_code' => $code,
                ])->save();
                break;
            }
            return $gift;
        });
    }

    public function send(MembershipGift $gift): void
    {
        Cache::lock('gift-mail:'.$gift->id, 120)->get(function () use ($gift): void {
            $gift->refresh();
            if (!$gift->paid_at || $gift->email_sent_at || !$gift->encrypted_code) return;
            $buyer = User::findOrFail($gift->buyer_id);
            $sender = $buyer->name;
            $message = $sender." sent you one prepaid year of Kroo+!\n\n";
            if ($gift->message) $message .= $gift->message."\n\n";
            $message .= "Your one-use gift code: ".$gift->encrypted_code."\n\n"
                ."Join or sign into Kroo, open Profile → Membership → Redeem a gift code, and enter this code. "
                ."Your year starts when you redeem it. No payment or recurring subscription is needed.\n\n"
                ."If you are new to Kroo, use ".KrooId::format($buyer->kroo_id)." as your referral code when joining. Then redeem the gift code above from your Profile.\n\n"
                ."Keep this gift code private. It can only be redeemed once.";
            Mail::raw($message, fn ($mail) => $mail->to($gift->recipient_email)->subject('You received a year of Kroo+!'));
            $gift->forceFill(['email_sent_at' => now()])->save();
        });
    }

    public function redeem(User $user, string $code): MembershipGift
    {
        $hash = hash('sha256', strtoupper(preg_replace('/[\s-]+/', '', $code)));
        return DB::transaction(function () use ($user, $hash) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $gift = MembershipGift::where('code_hash', $hash)->lockForUpdate()->first();
            abort_unless($gift && $gift->paid_at && !$gift->cancelled_at, 422, 'This gift code is invalid.');
            if ($gift->redeemed_at) {
                abort_unless($gift->redeemed_by === $user->id, 422, 'This gift code has already been redeemed.');
                return $gift; // A network retry never adds another year.
            }
            $gift->forceFill(['redeemed_by' => $user->id, 'redeemed_at' => now(), 'expires_at' => now()->addYear()])->save();
            $user->forceFill(['plan' => 'pro'])->save();
            return $gift;
        });
    }

    public function deliverPending(): void
    {
        MembershipGift::whereNull('cancelled_at')->whereNull('email_sent_at')
            ->where('created_at', '>=', now()->subDays(30))->each(function ($gift): void {
                try { $this->send($this->verify($gift)); } catch (\Throwable $error) { report($error); }
            });
        // Verified gifts are retried regardless of their age.
        MembershipGift::whereNotNull('paid_at')->whereNull('email_sent_at')->each(function ($gift): void {
            try { $this->send($gift); } catch (\Throwable $error) { report($error); }
        });
        User::where('plan', 'pro')->whereIn('id', MembershipGift::query()
            ->whereNotNull('redeemed_by')->where('expires_at', '<=', now())->select('redeemed_by'))
            ->each(fn ($user) => app(RevenueCatBilling::class)->status($user, false));
    }
}
