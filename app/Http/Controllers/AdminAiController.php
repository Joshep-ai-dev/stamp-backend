<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAiContent;
use App\Jobs\DiscoverAiSights;
use App\Models\City;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\Sight;
use App\Services\CountryResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminAiController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'configured' => filled(config('services.openai.api_key')),
            'batches' => DB::table('ai_content_batches')->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $batch = DB::table('ai_content_batches')->find($id);
        abort_unless($batch, 404);

        return response()->json([
            'batch' => $batch,
            'errors' => DB::table('ai_content_items')->where('batch_id', $id)->where('status', 'failed')
                ->orderBy('id')->limit(50)->get(['id', 'target_id', 'error']),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(config('queue.default') !== 'sync', 503, 'Set QUEUE_CONNECTION=database and start a queue worker.');
        abort_unless(filled(config('services.openai.api_key')), 503, 'Set OPENAI_API_KEY on the server.');
        $data = $request->validate([
            'category' => ['required', Rule::in(['countries', 'states', 'cities', 'sights', 'discover-sights'])],
            'cityCsv' => ['required_if:category,cities,discover-sights', 'nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $category = $data['category'];
        abort_if(DB::table('ai_content_batches')->where('category', $category)->where('status', 'running')->exists(), 409, 'A batch for this content type is already running.');
        $query = match ($category) {
            'countries' => Country::query()->where(fn ($q) => $q->whereNull('hero_image')->orWhere('hero_image', '')->orWhereNull('description')->orWhere('description', '')),
            'states' => CountryState::query()->where('country_code', 'US')->where(fn ($q) => $q->whereNull('image_url')->orWhere('image_url', '')->orWhereNull('description')->orWhere('description', '')),
            'cities' => City::query()->where(fn ($q) => $q->whereNull('image_url')->orWhere('image_url', '')->orWhereNull('description')->orWhere('description', '')),
            'sights' => Sight::query()->where('is_featured', true)->where(fn ($q) => $q->whereNull('image_url')->orWhere('image_url', '')->orWhereNull('description')->orWhere('description', '')),
            'discover-sights' => City::query(),
        };
        if (in_array($category, ['cities', 'discover-sights'], true)) {
            $rankedIds = $this->cityIdsFromCsv($data['cityCsv']->getRealPath());
            $eligible = $query->whereIn('id', $rankedIds)->pluck('id')->all();
            $ids = array_slice(array_values(array_intersect($rankedIds, $eligible)), 0, $data['limit'] ?? 10000);
        } else {
            $ids = $query->orderBy($category === 'countries' ? 'code' : 'id')->limit($data['limit'] ?? 10000)
                ->pluck($category === 'countries' ? 'code' : 'id')->all();
        }
        $batchId = DB::table('ai_content_batches')->insertGetId([
            'category' => $category, 'status' => $ids ? 'running' : 'complete',
            'total' => count($ids), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('ai_content_items')->insert(array_map(fn ($id) => [
                'batch_id' => $batchId, 'target_id' => (string) $id, 'status' => 'queued',
                'created_at' => now(), 'updated_at' => now(),
            ], $chunk));
        }
        DB::table('ai_content_items')->where('batch_id', $batchId)->orderBy('id')->pluck('id')
            ->each(fn ($id) => $this->dispatch($category, $id));

        return $this->show($batchId);
    }

    public function pause(int $id): JsonResponse
    {
        DB::table('ai_content_batches')->where('id', $id)->where('status', 'running')
            ->update(['status' => 'paused', 'updated_at' => now()]);

        return $this->show($id);
    }

    public function resume(int $id): JsonResponse
    {
        $batch = DB::table('ai_content_batches')->find($id);
        abort_unless($batch, 404);
        abort_unless($batch->status === 'paused' || $batch->failed > 0, 409, 'This batch has no paused or failed work.');
        DB::table('ai_content_batches')->where('id', $id)->update(['status' => 'running', 'updated_at' => now()]);
        DB::table('ai_content_items')->where('batch_id', $id)->where('status', 'failed')
            ->update(['status' => 'queued', 'error' => null, 'updated_at' => now()]);
        DB::table('ai_content_batches')->where('id', $id)->update(['failed' => 0]);
        DB::table('ai_content_items')->where('batch_id', $id)->whereIn('status', ['queued', 'working'])
            ->orderBy('id')->pluck('id')->each(fn ($itemId) => $this->dispatch($batch->category, $itemId));
        DB::table('ai_content_batches')->where('id', $id)->whereRaw('completed >= total')
            ->update(['status' => 'complete', 'updated_at' => now()]);

        return $this->show($id);
    }

    private function cityIdsFromCsv(string $path): array
    {
        $file = fopen($path, 'rb');
        if (! $file) {
            abort(422, 'Could not read the city CSV.');
        }
        try {
            $header = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($file) ?: []);
            foreach (['rank', 'city', 'country'] as $required) {
                abort_unless(in_array($required, $header, true), 422, 'CSV needs rank, city, and country columns.');
            }
            $rows = [];
            while (($values = fgetcsv($file)) !== false) {
                if (count($values) !== count($header)) {
                    abort(422, 'CSV has a malformed row.');
                }
                $row = array_combine($header, $values);
                if ((int) $row['rank'] >= 1 && (int) $row['rank'] <= 1000) {
                    $rows[(int) $row['rank']] = $row;
                }
            }
        } finally {
            fclose($file);
        }
        abort_unless(count($rows) === 1000, 422, 'The city CSV must contain ranks 1 through 1000.');
        ksort($rows);
        $resolver = app(CountryResolver::class);
        $ids = [];
        $missing = [];
        foreach ($rows as $row) {
            try {
                $code = $resolver->resolve($row['country'])['code'];
            } catch (\RuntimeException) {
                $code = null;
            }
            $name = Str::of($row['city'])->ascii()->lower()->squish()->toString();
            $matches = $code ? City::where('country_code', $code)->where('normalized_name', $name)->pluck('id') : collect();
            if ($matches->count() !== 1) {
                $missing[] = $row['city'].', '.$row['country'];
            } else {
                $ids[] = $matches->first();
            }
        }
        abort_if($missing, 422, 'Could not uniquely match '.count($missing).' cities. First: '.implode('; ', array_slice($missing, 0, 5)));

        return $ids;
    }

    private function dispatch(string $category, int $itemId): void
    {
        if ($category === 'discover-sights') {
            DiscoverAiSights::dispatch($itemId)->onQueue('ai-content');
        } else {
            GenerateAiContent::dispatch($itemId)->onQueue('ai-content');
        }
    }
}
