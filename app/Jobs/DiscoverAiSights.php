<?php

namespace App\Jobs;

use App\Exceptions\AiRateLimitedException;
use App\Models\City;
use App\Models\Sight;
use App\Services\AiStampGenerator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DiscoverAiSights implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public int $uniqueFor = 600;

    public function __construct(public int $itemId) {}

    public function uniqueId(): string
    {
        return (string) $this->itemId;
    }

    public function uniqueVia(): CacheRepository
    {
        return Cache::store('database');
    }

    public function handle(AiStampGenerator $generator): void
    {
        $lock = Cache::store('database')->lock('ai-content-item:'.$this->itemId, 1200);
        if (! $lock->get()) {
            return;
        }
        try {
            $this->generate($generator);
        } finally {
            $lock->release();
        }
    }

    private function generate(AiStampGenerator $generator): void
    {
        $item = DB::table('ai_content_items')->find($this->itemId);
        if (! $item || $item->status === 'complete') {
            return;
        }
        $batch = DB::table('ai_content_batches')->find($item->batch_id);
        if (! $batch || $batch->status !== 'running') {
            return;
        }
        DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'working', 'error' => null, 'updated_at' => now()]);
        $city = City::with('country')->findOrFail($item->target_id);
        $names = $generator->sights($city->name, $city->country?->name ?? $city->country_code);
        DB::transaction(function () use ($city, $names, $item): void {
            foreach ($names as $name) {
                Sight::firstOrCreate(
                    ['city_id' => $city->id, 'slug' => Str::slug($name)],
                    ['country_code' => $city->country_code, 'name' => $name, 'description' => '',
                        'image_url' => '', 'is_featured' => false, 'is_premium' => false, 'display_order' => 0],
                );
            }
            DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'complete', 'error' => null, 'updated_at' => now()]);
            DB::table('ai_content_batches')->where('id', $item->batch_id)->increment('completed');
        });
        $this->finishBatch($item->batch_id);
    }

    public function failed(Throwable $exception): void
    {
        if ($exception instanceof AiRateLimitedException) {
            DB::table('ai_content_items')->where('id', $this->itemId)->where('status', 'working')
                ->update(['status' => 'queued', 'error' => $exception->getMessage(), 'updated_at' => now()]);

            return;
        }
        $item = DB::table('ai_content_items')->find($this->itemId);
        if (! $item || in_array($item->status, ['complete', 'failed'], true)) {
            return;
        }
        DB::transaction(function () use ($item, $exception): void {
            DB::table('ai_content_items')->where('id', $item->id)->update([
                'status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 1000), 'updated_at' => now(),
            ]);
            DB::table('ai_content_batches')->where('id', $item->batch_id)->increment('failed');
        });
        $this->finishBatch($item->batch_id);
    }

    private function finishBatch(int $id): void
    {
        DB::table('ai_content_batches')->where('id', $id)->where('status', 'running')
            ->whereRaw('completed + failed >= total')->update(['status' => 'complete', 'updated_at' => now()]);
    }
}
