<?php

namespace App\Services;

use App\Exceptions\AiRateLimitedException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiRateLimit
{
    private static function key(): string
    {
        return 'ai-rate-limit:'.hash('sha256', (string) config('services.openai.api_key'));
    }

    private static function state(): array
    {
        return Cache::store('database')->get(self::key(), []);
    }

    public static function retryAfter(): int
    {
        return max(0, (int) (self::state()['until'] ?? 0) - now()->timestamp);
    }

    public static function concurrency(int $configured): int
    {
        return max(1, min($configured, (int) (self::state()['concurrency'] ?? $configured)));
    }

    public static function isTemporary(Response $response): bool
    {
        if ($response->status() !== 429) {
            return false;
        }
        $code = strtolower((string) $response->json('error.code'));
        $message = strtolower((string) $response->json('error.message'));

        return ! in_array($code, ['insufficient_quota', 'billing_hard_limit_reached', 'billing_not_active', 'usage_limit_reached'], true)
            && ! str_contains($message, 'current quota') && ! str_contains($message, 'billing') && ! str_contains($message, 'insufficient credit');
    }

    public static function pause(Response $response): AiRateLimitedException
    {
        $lock = Cache::store('database')->lock(self::key().':lock', 10);

        return $lock->block(5, function () use ($response): AiRateLimitedException {
            $state = self::state();
            $active = ($state['until'] ?? 0) > now()->timestamp;
            $attempt = $active ? ($state['attempt'] ?? 1) : min(5, ($state['attempt'] ?? 0) + 1);
            $retryAfter = $response->header('Retry-After');
            $delay = is_numeric($retryAfter) ? (float) $retryAfter : max(0, (strtotime($retryAfter ?? '') ?: 0) - now()->timestamp);
            if ($response->header('retry-after-ms')) {
                $delay = max($delay, (float) $response->header('retry-after-ms') / 1000);
            }
            foreach (['x-ratelimit-reset-requests', 'x-ratelimit-reset-tokens'] as $header) {
                $value = $response->header($header) ?? '';
                if (preg_match_all('/([\d.]+)(ms|s|m|h)/', $value, $matches, PREG_SET_ORDER)) {
                    $seconds = 0;
                    foreach ($matches as $match) {
                        $seconds += (float) $match[1] * match ($match[2]) {
                            'ms' => 0.001, 's' => 1, 'm' => 60, 'h' => 3600
                        };
                    }
                    $delay = max($delay, $seconds);
                }
            }
            if (preg_match('/try again in\s+([\d.]+)\s*(ms|s|m|h)/i', (string) $response->json('error.message'), $match)) {
                $delay = max($delay, (float) $match[1] * match (strtolower($match[2])) {
                    'ms' => 0.001, 's' => 1, 'm' => 60, 'h' => 3600
                });
            }
            $delay = max(1, (int) ceil($delay > 0 ? $delay : min(300, 30 * (2 ** ($attempt - 1)))));
            $until = max((int) ($state['until'] ?? 0), now()->timestamp + $delay);
            $configured = max(1, min(12, (int) config('ai.concurrency', 8)));
            $concurrency = self::concurrency($configured);
            Cache::store('database')->put(self::key(), [
                'until' => $until, 'attempt' => $attempt,
                'concurrency' => $active ? $concurrency : max(1, (int) floor($concurrency / 2)),
                'lastIncrease' => now()->timestamp,
            ], max(3600, $until - now()->timestamp + 3600));

            return new AiRateLimitedException($until);
        });
    }

    public static function succeeded(): void
    {
        Cache::store('database')->lock(self::key().':lock', 10)->block(5, function (): void {
            $state = self::state();
            if (! $state || self::retryAfter() > 0) {
                return;
            }
            $configured = max(1, min(12, (int) config('ai.concurrency', 8)));
            $state['attempt'] = 0;
            if (now()->timestamp - ($state['lastIncrease'] ?? 0) >= 60) {
                $state['concurrency'] = min($configured, ($state['concurrency'] ?? 1) + 1);
                $state['lastIncrease'] = now()->timestamp;
            }
            Cache::store('database')->put(self::key(), $state, 3600);
        });
    }

    public static function recoverFailedItems(): int
    {
        $items = DB::table('ai_content_items as items')->join('ai_content_batches as batches', 'batches.id', '=', 'items.batch_id')
            ->where('items.status', 'failed')->whereIn('batches.status', ['running', 'complete'])
            ->where(fn ($q) => $q->whereRaw("LOWER(items.error) LIKE '%rate limit reached%'")->orWhereRaw("LOWER(items.error) LIKE '%rate_limit_exceeded%'"))
            ->get(['items.id', 'items.batch_id', 'items.error'])->filter(fn ($item) => ! preg_match('/quota|billing|insufficient credit/i', $item->error));
        $recovered = 0;
        foreach ($items->groupBy('batch_id') as $batchId => $group) {
            $recovered += DB::transaction(function () use ($batchId, $group): int {
                $batch = DB::table('ai_content_batches')->where('id', $batchId)->lockForUpdate()->first();
                if (! $batch || ! in_array($batch->status, ['running', 'complete'], true)) {
                    return 0;
                }
                $changed = DB::table('ai_content_items')->whereIn('id', $group->pluck('id'))->where('status', 'failed')
                    ->update(['status' => 'queued', 'error' => null, 'updated_at' => now()]);
                if ($changed) {
                    DB::table('ai_content_batches')->where('id', $batchId)->update([
                        'failed' => DB::table('ai_content_items')->where('batch_id', $batchId)->where('status', 'failed')->count(),
                        'status' => 'running', 'updated_at' => now(),
                    ]);
                }

                return $changed;
            });
        }

        return $recovered;
    }
}
