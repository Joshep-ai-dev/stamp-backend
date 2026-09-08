<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RevenueCatBilling;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function show(Request $request, RevenueCatBilling $billing): JsonResponse
    {
        return response()->json($billing->status($request->user()));
    }

    public function sync(Request $request, RevenueCatBilling $billing): JsonResponse
    {
        abort_unless($billing->configured(), Response::HTTP_SERVICE_UNAVAILABLE, 'RevenueCat is not configured.');

        return response()->json($billing->syncUser($request->user()));
    }

    public function webhook(Request $request, RevenueCatBilling $billing): JsonResponse
    {
        $this->authorizeWebhook($request);
        $event = $request->validate([
            'event' => ['required', 'array'],
            'event.id' => ['nullable', 'string', 'max:255'],
            'event.type' => ['nullable', 'string', 'max:100'],
            'event.app_user_id' => ['nullable', 'string', 'max:255'],
            'event.aliases' => ['sometimes', 'array'],
            'event.aliases.*' => ['string', 'max:255'],
        ])['event'];

        $identifiers = collect([$event['app_user_id'] ?? null, ...($event['aliases'] ?? [])])
            ->filter(fn ($id): bool => is_string($id) && Str::isUuid($id))
            ->unique();
        $user = User::whereIn('id', $identifiers)->first();
        if ($user) {
            $eventId = $event['id'] ?? null;
            if ($eventId === null || $user->revenueCatEntitlement?->last_event_id !== $eventId) {
                $billing->syncUser($user, $eventId, $event['type'] ?? null);
            }
        }

        return response()->json(['received' => true]);
    }

    private function authorizeWebhook(Request $request): void
    {
        $authorization = trim((string) config('services.revenuecat.webhook_authorization'));
        $signingSecret = trim((string) config('services.revenuecat.webhook_signing_secret'));
        abort_if($authorization === '' && $signingSecret === '', Response::HTTP_SERVICE_UNAVAILABLE, 'RevenueCat webhook authentication is not configured.');

        if ($authorization !== '') {
            abort_unless(hash_equals($authorization, (string) $request->header('Authorization')), Response::HTTP_UNAUTHORIZED);
        }
        if ($signingSecret !== '') {
            $parts = [];
            foreach (explode(',', (string) $request->header('X-RevenueCat-Webhook-Signature')) as $part) {
                if (str_contains($part, '=')) {
                    [$key, $value] = explode('=', $part, 2);
                    $parts[trim($key)] = trim($value);
                }
            }
            $timestamp = $parts['t'] ?? '';
            $signature = $parts['v1'] ?? '';
            $expected = hash_hmac('sha256', "{$timestamp}.{$request->getContent()}", $signingSecret);
            abort_unless($timestamp !== '' && $signature !== '' && hash_equals($expected, $signature), Response::HTTP_UNAUTHORIZED);
            abort_if(abs(time() - (int) $timestamp) > 300, Response::HTTP_UNAUTHORIZED);
        }
    }
}
