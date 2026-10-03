<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;

class DreamVacationChallenge
{
    public function progress(User $user): array
    {
        $entitlement = $user->revenueCatEntitlement;
        $startedAt = $entitlement?->paid_membership_started_at;
        $deadline = $startedAt === null ? null : CarbonImmutable::instance($startedAt)->addMonthsNoOverflow(12);
        $active = $entitlement?->is_active === true
            && ($entitlement->expires_at === null || $entitlement->expires_at->isFuture());
        $score = 0.0;
        $iq = 0.0;
        $referrals = 0;

        if ($startedAt !== null && $startedAt->lte(now())) {
            $end = $deadline->min(now());
            // Scope challenge points without changing the lifetime passport score.
            $participant = clone $user;
            $participant->setRelation('visits', $user->visits()->whereBetween('created_at', [$startedAt, $end])->get());
            $participant->setRelation('completions', $user->completions()->whereBetween('completed_at', [$startedAt, $end])->get());
            $participant->setRelation('rewards', $user->rewards()->where('unlocked', true)->whereBetween('updated_at', [$startedAt, $end])->get());
            $score = KrooScore::for($participant)['score'];
            $iq = round((float) $user->krooIqAttempts()
                ->where('created_at', '>=', $startedAt)
                ->whereBetween('completed_at', [$startedAt, $end])
                ->get()
                ->sum(fn ($attempt) => max(0, (float) $attempt->score_after - (float) $attempt->score_before)), 2);
            // Joining Kroo is sufficient; no referred subscription is required.
            $referrals = $user->referrals()->whereBetween('created_at', [$startedAt, $end])->count();
        }

        $targetsMet = $score >= 5 && $iq >= 85 && $referrals >= 5;

        return [
            'krooScore' => $score,
            'krooScoreTarget' => 5,
            'krooScoreQualified' => $score >= 5,
            'krooIqScore' => $iq,
            'krooIqTarget' => 85,
            'krooIqQualified' => $iq >= 85,
            'referralCount' => $referrals,
            'referralTarget' => 5,
            'referralsQualified' => $referrals >= 5,
            'startedAt' => $startedAt?->toIso8601String(),
            'deadlineAt' => $deadline?->toIso8601String(),
            'expired' => $deadline !== null && now()->gt($deadline),
            'membershipActive' => $active,
            'qualified' => $active && $targetsMet,
        ];
    }
}
