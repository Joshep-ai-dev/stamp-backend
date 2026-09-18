<?php

namespace App\Services;

use App\Models\RevenueCatEntitlement;

class KrooPlusReferralQualification
{
    public function qualifyEligibleMemberships(): int
    {
        $months = max(1, (int) config('services.revenuecat.referral_qualification_months', 3));

        return RevenueCatEntitlement::query()
            ->whereNull('referral_qualified_at')
            ->where('is_active', true)
            ->whereNotNull('paid_membership_started_at')
            ->where('paid_membership_started_at', '<=', now()->subMonthsNoOverflow($months))
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->update(['referral_qualified_at' => now()]);
    }
}
