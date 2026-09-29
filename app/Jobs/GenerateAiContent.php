<?php

namespace App\Jobs;

use App\Exceptions\AiRateLimitedException;
use App\Models\City;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\Sight;
use App\Services\AiRateLimit;
use App\Services\AiStampGenerator;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateAiContent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 2;

    public int $uniqueFor = 900;

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

    public function prepareContent(): ?array
    {
        $item = DB::table('ai_content_items')->find($this->itemId);
        if (! $item || $item->status === 'complete') {
            return null;
        }
        $batch = DB::table('ai_content_batches')->find($item->batch_id);
        if (! $batch || $batch->status !== 'running') {
            return null;
        }
        DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'working', 'error' => null, 'updated_at' => now()]);
        $model = match ($batch->category) {
            'countries' => Country::find($item->target_id),
            'states' => CountryState::find($item->target_id),
            'cities' => City::with('country')->find($item->target_id),
            'sights' => Sight::with(['city.country'])->find($item->target_id),
        };
        if (! $model) {
            throw new \RuntimeException('Catalog record no longer exists.');
        }
        $name = match ($batch->category) {
            'countries' => $model->name,
            'states' => $model->name.', United States',
            'cities' => $model->name.', '.($model->country?->name ?? $model->country_code),
            'sights' => $model->name.', '.($model->city?->name ?? '').', '.($model->city?->country?->name ?? $model->country_code),
        };
        $category = match ($batch->category) {
            'countries' => 'Country', 'states' => 'State', 'cities' => 'City', 'sights' => 'Top Sight',
        };
        $imageField = $batch->category === 'countries' ? 'hero_image' : 'image_url';

        return compact('model', 'category', 'name', 'imageField') + ['folder' => $batch->category];
    }

    private function generate(AiStampGenerator $generator): void
    {
        $context = $this->prepareContent();
        if (! $context) {
            return;
        }
        ['model' => $model, 'category' => $category, 'name' => $name, 'imageField' => $imageField] = $context;
        if ($category !== 'Country' && blank($model->description)) {
            $model->description = $generator->description($category, $name);
            $model->save();
        }
        if (blank($model->{$imageField})) {
            $url = $generator->image($category, $name, $context['folder']);
            $model->{$imageField} = $url;
            $model->save();
        }
        $this->completeItem();
    }

    public function saveContent(array $context, array $result, bool $complete = true): void
    {
        $model = $context['model'];
        $imageField = $context['imageField'];
        $model->refresh();
        if (! $model instanceof Country && isset($result['description']) && blank($model->description)) {
            $model->description = $result['description'];
        }
        if (isset($result['image']) && blank($model->{$imageField})) {
            $model->{$imageField} = $result['image'];
        }
        $model->save();
        if (isset($result['error'])) {
            throw $result['error'];
        }
        if ((! $model instanceof Country && blank($model->description)) || blank($model->{$imageField})) {
            throw new \RuntimeException('Required content generation did not finish.');
        }
        if ($complete) {
            $this->completeItem();
        }
    }

    private function completeItem(): void
    {
        $item = DB::table('ai_content_items')->find($this->itemId);
        if (! $item) {
            return;
        }
        DB::transaction(function () use ($item): void {
            $changed = DB::table('ai_content_items')->where('id', $item->id)->where('status', 'working')
                ->update(['status' => 'complete', 'error' => null, 'updated_at' => now()]);
            if ($changed) {
                DB::table('ai_content_batches')->where('id', $item->batch_id)->increment('completed');
            }
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
        if (AiRateLimit::isBillingFailure($exception)) {
            DB::table('ai_content_batches')->where('id', $item->batch_id)->where('status', 'running')
                ->update(['status' => 'paused', 'updated_at' => now()]);
        }
        $this->finishBatch($item->batch_id);
    }

    private function finishBatch(int $id): void
    {
        DB::table('ai_content_batches')->where('id', $id)->where('status', 'running')
            ->whereRaw('completed + failed >= total')->update(['status' => 'complete', 'updated_at' => now()]);
    }
}
