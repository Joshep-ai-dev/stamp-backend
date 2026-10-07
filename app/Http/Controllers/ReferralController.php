<?php

namespace App\Http\Controllers;

use App\Models\MembershipGift;
use App\Services\KrooId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $buyer = $request->user();
        $members = $buyer->referrals()->select(['id', 'name', 'kroo_id', 'created_at'])
            ->orderByDesc('created_at')->orderBy('id')->paginate(50);
        $giftMembers = MembershipGift::where('buyer_id', $buyer->id)
            ->whereNotNull('paid_at')->whereNotNull('redeemed_at')
            ->whereIn('redeemed_by', $members->getCollection()->pluck('id'))
            ->pluck('redeemed_by')->all();

        return response()->json([
            'members' => $members->getCollection()->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'krooId' => KrooId::format($member->kroo_id),
                'joinedAt' => $member->created_at->toIso8601String(),
                'source' => in_array($member->id, $giftMembers, true) ? 'gift' : 'referral',
            ])->values(),
            'total' => $members->total(),
            'nextPage' => $members->hasMorePages() ? $members->currentPage() + 1 : null,
        ]);
    }
}
