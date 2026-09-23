<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class KrooPlusReferralEligibility
{
    private const ANNUAL_MINIMUM_DAYS = 300;

    public function qualifies(
        bool $active,
        ?CarbonInterface $paidMembershipStartedAt,
        array $subscription,
        ?string $planId = null,
    ): bool {
        if (! $active
            || $paidMembershipStartedAt === null
            || strtolower((string) ($subscription['period_type'] ?? '')) !== 'normal') {
            return false;
        }

        return $this->isAnnual($subscription, $planId)
            || $paidMembershipStartedAt->lte(
                now()->subMonthsNoOverflow(
                    max(1, (int) config('services.revenuecat.referral_qualification_months', 3)),
                ),
            );
    }

    public function isAnnual(array $subscription, ?string $planId = null): bool
    {
        if ($planId !== null
            && (str_contains(strtolower($planId), 'annual')
                || str_contains(strtolower($planId), 'yearly'))) {
            return true;
        }

        $purchasedAt = $subscription['purchase_date'] ?? null;
        $expiresAt = $subscription['expires_date'] ?? null;
        if (! is_string($purchasedAt) || ! is_string($expiresAt)) {
            return false;
        }

        return CarbonImmutable::parse($purchasedAt)
            ->diffInDays(CarbonImmutable::parse($expiresAt)) >= self::ANNUAL_MINIMUM_DAYS;
    }

    public function subscriptionFor(array $subscriptions, ?string $productId, ?string $planId): array
    {
        if ($productId === null) {
            return [null, []];
        }

        $identifiers = array_filter([
            $planId !== null ? "{$productId}:{$planId}" : null,
            $productId,
        ]);
        foreach ($identifiers as $identifier) {
            $subscription = $subscriptions[$identifier] ?? null;
            if (is_array($subscription)) {
                return [$identifier, $subscription];
            }
        }

        return [null, []];
    }
}
