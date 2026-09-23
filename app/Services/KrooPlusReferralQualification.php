<?php

namespace App\Services;

use App\Models\RevenueCatEntitlement;

class KrooPlusReferralQualification
{
    public function __construct(private readonly KrooPlusReferralEligibility $eligibility) {}

    public function qualifyEligibleMemberships(): int
    {
        $qualified = 0;
        RevenueCatEntitlement::query()
            ->whereNull('referral_qualified_at')
            ->where('is_active', true)
            ->whereNotNull('paid_membership_started_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->each(function (RevenueCatEntitlement $entitlement) use (&$qualified): void {
                $payload = $entitlement->subscriber_payload ?? [];
                $entitlementPayload = $payload['subscriber']['entitlements'][$entitlement->entitlement_id] ?? [];
                $productId = $entitlementPayload['product_identifier'] ?? $entitlement->product_id;
                $planId = $entitlementPayload['product_plan_identifier'] ?? null;
                [, $subscription] = $this->eligibility->subscriptionFor(
                    $payload['subscriber']['subscriptions'] ?? [],
                    is_string($productId) ? $productId : null,
                    is_string($planId) ? $planId : null,
                );

                if ($this->eligibility->qualifies(
                    true,
                    $entitlement->paid_membership_started_at,
                    $subscription,
                )) {
                    $entitlement->forceFill(['referral_qualified_at' => now()])->save();
                    $qualified++;
                }
            });

        return $qualified;
    }
}
