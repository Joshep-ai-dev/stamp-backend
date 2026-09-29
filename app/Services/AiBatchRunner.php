<?php

namespace App\Services;

use App\Exceptions\AiRateLimitedException;
use App\Jobs\DiscoverAiSights;
use App\Jobs\GenerateAiContent;
use App\Models\City;
use App\Models\Sight;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AiBatchRunner
{
    public function processNext(int $id): bool
    {
        if (AiRateLimit::retryAfter() > 0) {
            return false;
        }
        $lock = Cache::store('database')->lock('ai-content-batch:'.$id, 1200);
        if (! $lock->get()) {
            return false;
        }
        $itemLocks = [];
        $contexts = [];
        try {
            $batch = DB::table('ai_content_batches')->find($id);
            if (! $batch || $batch->status !== 'running') {
                return false;
            }
            if (DB::table('ai_content_items')->where('batch_id', $id)->where('status', 'working')
                ->where('updated_at', '>', now()->subMinutes(20))->exists()) {
                return false;
            }
            $items = DB::table('ai_content_items')->where('batch_id', $id)
                ->whereIn('status', ['queued', 'working'])->orderBy('id')
                ->limit(AiStampGenerator::concurrency())->get();
            if ($items->isEmpty()) {
                $this->finishBatch($id);

                return false;
            }
            $generator = app(AiStampGenerator::class);
            $tasks = [];
            foreach ($items as $item) {
                $itemLock = Cache::store('database')->lock('ai-content-item:'.$item->id, 1200);
                if (! $itemLock->get()) {
                    continue;
                }
                $itemLocks[] = $itemLock;
                $job = $batch->category === 'discover-sights'
                    ? new DiscoverAiSights($item->id) : new GenerateAiContent($item->id);
                try {
                    if ($batch->category === 'discover-sights') {
                        $city = City::with(['country', 'sights'])->findOrFail($item->target_id);
                        DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'working', 'error' => null, 'updated_at' => now()]);
                        $context = ['city' => $city, 'job' => $job];
                        $tasks[$item->id] = ['discover' => $city->sights->isEmpty(), 'name' => $city->name.', '.($city->country?->name ?? $city->country_code)];
                    } else {
                        $context = $job->prepareContent();
                        if (! $context) {
                            continue;
                        }
                        $context['job'] = $job;
                        $tasks[$item->id] = $this->task($context);
                    }
                    $contexts[$item->id] = $context;
                } catch (Throwable $exception) {
                    $job->failed($exception);
                }
            }
            $results = $generator->generateMany($tasks);
            if ($batch->category === 'discover-sights') {
                $this->saveDiscovery($contexts, $results, $generator);
            } else {
                foreach ($contexts as $itemId => $context) {
                    try {
                        $context['job']->saveContent($context, $results[$itemId] ?? []);
                    } catch (Throwable $exception) {
                        $context['job']->failed($exception);
                    }
                }
            }
            $this->finishBatch($id);

            return DB::table('ai_content_items')->whereIn('id', $items->pluck('id'))
                ->whereIn('status', ['complete', 'failed'])->exists();
        } catch (Throwable $exception) {
            foreach ($contexts as $context) {
                $context['job']->failed($exception);
            }
            throw $exception;
        } finally {
            foreach ($itemLocks as $itemLock) {
                $itemLock->release();
            }
            $lock->release();
        }
    }

    private function task(array $context): array
    {
        return [
            'category' => $context['category'], 'name' => $context['name'], 'folder' => $context['folder'],
            'description' => $context['category'] !== 'Country' && blank($context['model']->description),
            'image' => blank($context['model']->{$context['imageField']}),
        ];
    }

    private function saveDiscovery(array $contexts, array $results, AiStampGenerator $generator): void
    {
        $sights = [];
        $tasks = [];
        $errors = [];
        foreach ($contexts as $itemId => $context) {
            try {
                if (isset($results[$itemId]['error'])) {
                    throw $results[$itemId]['error'];
                }
                $city = $context['city'];
                $names = $city->sights->isNotEmpty() ? $city->sights->pluck('name')->all() : ($results[$itemId]['names'] ?? []);
                foreach ($names as $name) {
                    $sight = Sight::firstOrCreate(
                        ['city_id' => $city->id, 'slug' => Str::slug($name)],
                        ['country_code' => $city->country_code, 'name' => $name, 'description' => '',
                            'image_url' => '', 'is_featured' => false, 'is_premium' => false, 'display_order' => 0],
                    );
                    $key = 'sight-'.$itemId.'-'.$sight->id;
                    $sights[$key] = ['model' => $sight, 'itemId' => $itemId, 'category' => 'Top Sight',
                        'name' => $sight->name.', '.$city->name.', '.($city->country?->name ?? $city->country_code),
                        'folder' => 'sights', 'imageField' => 'image_url'];
                    $tasks[$key] = $this->task($sights[$key]);
                }
                if ($city->sights->isEmpty() && count($names) !== 5) {
                    throw new \RuntimeException('Sight discovery did not return five names.');
                }
            } catch (Throwable $exception) {
                $errors[$itemId] = $exception;
            }
        }
        $content = $generator->generateMany($tasks);
        foreach ($sights as $key => $context) {
            try {
                (new GenerateAiContent($context['itemId']))->saveContent($context, $content[$key] ?? [], false);
            } catch (Throwable $exception) {
                $errors[$context['itemId']] ??= $exception instanceof AiRateLimitedException ? $exception : new \RuntimeException($context['model']->name.': '.$exception->getMessage(), 0, $exception);
            }
        }
        foreach ($contexts as $itemId => $context) {
            if (isset($errors[$itemId])) {
                $context['job']->failed($errors[$itemId]);

                continue;
            }
            DB::transaction(function () use ($itemId): void {
                $item = DB::table('ai_content_items')->where('id', $itemId)->lockForUpdate()->first();
                if (! $item || $item->status !== 'working') {
                    return;
                }
                DB::table('ai_content_items')->where('id', $itemId)->update(['status' => 'complete', 'error' => null, 'updated_at' => now()]);
                DB::table('ai_content_batches')->where('id', $item->batch_id)->increment('completed');
            });
        }
    }

    private function finishBatch(int $id): void
    {
        DB::table('ai_content_batches')->where('id', $id)->where('status', 'running')
            ->whereRaw('completed + failed >= total')->update(['status' => 'complete', 'updated_at' => now()]);
    }
}
