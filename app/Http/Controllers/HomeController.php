<?php

namespace App\Http\Controllers;

use App\Services\KrooPlusReferralQualification;
use App\Services\KrooScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function show(Request $request, KrooPlusReferralQualification $referralQualification): JsonResponse
    {
        $referralQualification->qualifyEligibleMemberships();
        $user = $request->user();
        $summary = KrooScore::for($user);
        $visits = $user->visits;
        $krooIqScore = round((float) ($user->kroo_iq_score ?? 0), 2);
        $referralCount = $user->referrals()
            ->whereHas('revenueCatEntitlement', fn ($query) => $query->whereNotNull('referral_qualified_at'))
            ->count();
        $challengeProgress = [
            'krooScore' => $summary['score'],
            'krooScoreTarget' => 5,
            'krooScoreQualified' => $summary['score'] >= 5,
            'krooIqScore' => $krooIqScore,
            'krooIqTarget' => 80,
            'krooIqQualified' => $krooIqScore >= 80,
            'referralCount' => $referralCount,
            'referralTarget' => 5,
            'referralsQualified' => $referralCount >= 5,
        ];
        $challengeProgress['qualified'] = $challengeProgress['krooScoreQualified']
            && $challengeProgress['krooIqQualified']
            && $challengeProgress['referralsQualified'];
        $continentCounts = collect(['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'])->mapWithKeys(fn ($code) => [$code => $visits->where('continent_code', $code)->pluck('country_code')->unique()->count()]);

        return response()->json([
            'counts' => collect($summary['counts'])->except('collections')->all(),
            'score' => $summary['score'], 'level' => $summary['level'], 'challengePoints' => $summary['challengePoints'],
            'worldProgress' => (int) round($summary['counts']['countries'] / 195 * 100), 'visitedCountryCodes' => $summary['visitedCountryCodes'],
            'continentCounts' => $continentCounts, 'challengeProgress' => $challengeProgress,
            'updatedAt' => now()->utc()->toISOString(),
        ]);
    }
}
