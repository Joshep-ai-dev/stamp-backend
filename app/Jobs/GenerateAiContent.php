<?php

namespace App\Jobs;

use App\Models\City;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\Sight;
use App\Services\AiStampGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateAiContent implements ShouldQueue, ShouldBeUnique
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

    public function handle(AiStampGenerator $generator): void
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
        if (blank($model->description)) {
            $model->description = $generator->description($category, $name);
            $model->save();
        }
        if (blank($model->{$imageField})) {
            $url = $generator->image($category, $name, $batch->category);
            $model->{$imageField} = $url;
            $model->save();
        }
        DB::transaction(function () use ($item): void {
            DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'complete', 'error' => null, 'updated_at' => now()]);
            DB::table('ai_content_batches')->where('id', $item->batch_id)->increment('completed');
        });
        $this->finishBatch($item->batch_id);
    }

    public function failed(Throwable $exception): void
    {
        $item = DB::table('ai_content_items')->find($this->itemId);
        if (! $item || $item->status === 'failed') {
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
