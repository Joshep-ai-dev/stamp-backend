<?php

namespace App\Http\Controllers;

use App\Models\MembershipGift;
use App\Services\MembershipGifts;
use App\Services\RevenueCatBilling;
use Illuminate\Http\Request;

class MembershipGiftController extends Controller
{
    public function store(Request $request, MembershipGifts $gifts)
    {
        $data = $request->validate([
            'recipientEmail' => ['required', 'email:rfc', 'max:254'],
            'message' => ['nullable', 'string', 'max:240'], 'productId' => ['required', 'string', 'max:255'],
        ]);
        return response()->json($this->payload($gifts->create($request->user(), $data)));
    }
    public function verify(Request $request, MembershipGift $gift, MembershipGifts $gifts)
    {
        abort_unless($gift->buyer_id === $request->user()->id, 404);
        $gift = $gifts->verify($gift);
        try { $gifts->send($gift); } catch (\Throwable $error) { report($error); }
        return response()->json($this->payload($gift->fresh()));
    }
    public function destroy(Request $request, MembershipGift $gift, MembershipGifts $gifts)
    {
        abort_unless($gift->buyer_id === $request->user()->id, 404);
        $gift = $gifts->verify($gift);
        // Conditional update avoids racing with a verified payment.
        MembershipGift::whereKey($gift->id)->whereNull('paid_at')->update(['cancelled_at' => now()]);
        return response()->noContent();
    }
    public function redeem(Request $request, MembershipGifts $gifts, RevenueCatBilling $billing)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $gifts->redeem($request->user(), $data['code']);
        return response()->json($billing->status($request->user()->fresh(), false));
    }
    private function payload(MembershipGift $gift): array
    {
        return ['id' => $gift->id, 'recipientEmail' => $gift->recipient_email,
            'checkoutAllowed' => $gift->getAttribute('checkout_allowed') === true,
            'emailSent' => $gift->email_sent_at !== null,
            'status' => $gift->redeemed_at ? 'redeemed' : ($gift->paid_at ? 'paid' : 'pending')];
    }
}
