<?php

namespace App\Services;

use App\Models\RevenueCatEntitlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RevenueCatBilling
{
    public function configured(): bool
    {
        return trim((string) config('services.revenuecat.secret_api_key')) !== '';
    }

    public function syncUser(User $user, ?string $eventId = null, ?string $eventType = null): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('RevenueCat server credentials are not configured.');
        }

        $response = Http::acceptJson()
            ->withToken((string) config('services.revenuecat.secret_api_key'))
            ->timeout(15)
            ->retry(2, 250)
            ->get('https://api.revenuecat.com/v1/subscribers/'.rawurlencode($user->id));

        if ($response->status() === 404) {
            $payload = ['subscriber' => ['entitlements' => [], 'subscriptions' => []]];
        } else {
            $payload = $response->throw()->json();
        }

        $entitlementId = (string) config('services.revenuecat.entitlement_id', 'kroo_plus');
        $entitlement = $payload['subscriber']['entitlements'][$entitlementId] ?? null;
        $entitlement = is_array($entitlement) ? $entitlement : null;
        $productId = $entitlement['product_identifier'] ?? null;
        $subscription = is_string($productId)
            ? ($payload['subscriber']['subscriptions'][$productId] ?? null)
            : null;
        $subscription = is_array($subscription) ? $subscription : [];
        $expiry = $this->latestExpiry($entitlement, $subscription);
        $active = $entitlement !== null && ($expiry === null || $expiry->isFuture());

        $attributes = [
            'app_user_id' => $user->id,
            'entitlement_id' => $entitlementId,
            'product_id' => $productId,
            'store' => $subscription['store'] ?? null,
            'period_type' => $subscription['period_type'] ?? null,
            'is_active' => $active,
            'expires_at' => $expiry,
            'last_verified_at' => now(),
            'subscriber_payload' => $payload,
        ];
        if ($eventId !== null) {
            $attributes['last_event_id'] = $eventId;
            $attributes['last_event_type'] = $eventType;
        }
        RevenueCatEntitlement::updateOrCreate(['user_id' => $user->id], $attributes);
        $user->forceFill(['plan' => $active ? 'pro' : 'free'])->save();

        return $this->entitlement($user);
    }

    public function status(User $user, bool $refreshStale = true): array
    {
        $record = $user->revenueCatEntitlement;
        if ($refreshStale && $this->configured() && (! $record || $record->last_verified_at->lt(now()->subMinutes(5)))) {
            return $this->syncUser($user);
        }

        $active = $record?->is_active === true
            && ($record->expires_at === null || $record->expires_at->isFuture());
        $user->forceFill(['plan' => $active ? 'pro' : 'free'])->save();

        return $this->entitlement($user->fresh());
    }

    private function entitlement(User $user): array
    {
        $record = $user->revenueCatEntitlement()->first();
        $active = $record?->is_active === true
            && ($record->expires_at === null || $record->expires_at->isFuture());

        return [
            'plan' => $active ? 'pro' : 'free',
            'isKrooPlus' => $active,
            'productId' => $active ? $record?->product_id : null,
            'basePlanId' => null,
            'expiresAt' => $active ? $record?->expires_at?->toIso8601String() : null,
        ];
    }

    private function latestExpiry(?array $entitlement, array $subscription): ?CarbonImmutable
    {
        if ($entitlement === null) return null;
        $dates = collect([
            $entitlement['expires_date'] ?? null,
            $entitlement['grace_period_expires_date'] ?? null,
            $subscription['expires_date'] ?? null,
            $subscription['grace_period_expires_date'] ?? null,
        ])->filter()->map(fn (string $date): CarbonImmutable => CarbonImmutable::parse($date));

        return $dates->sortByDesc(fn (CarbonImmutable $date): int => $date->getTimestamp())->first();
    }
}
