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
    private const CITY_ALIASES = [
        'new york city' => 'new york',
        'washington, dc' => 'washington',
        'luxembourg city' => 'luxembourg',
        'frankfurt am main' => 'frankfurt',
        'ghent' => 'gent',
        'hanover' => 'hannover',
        'seville' => 'sevilla',
        'palma de mallorca' => 'palma',
    ];

    public function index(): JsonResponse
    {
        $waitingForWorker = DB::table('ai_content_batches')
            ->where('status', 'running')->where('completed', 0)->where('failed', 0)
            ->where('created_at', '<', now()->subMinutes(2))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('ai_content_items')
                ->whereColumn('ai_content_items.batch_id', 'ai_content_batches.id')
                ->where('ai_content_items.status', 'working'))
            ->exists();

        return response()->json([
            'configured' => filled(config('services.openai.api_key')),
            'batches' => DB::table('ai_content_batches')->orderByDesc('id')->limit(20)->get(),
            'waitingForWorker' => $waitingForWorker,
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
            $eligible = [];
            foreach (array_chunk($rankedIds, 400) as $chunk) {
                $eligible = array_merge($eligible, (clone $query)->whereIn('id', $chunk)->pluck('id')->all());
            }
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
        $resolved = [];
        $unknownCountries = [];
        foreach ($rows as $rank => $row) {
            try {
                $resolved[$rank] = ['row' => $row, 'country' => $resolver->resolve($row['country'])];
            } catch (\RuntimeException) {
                $unknownCountries[] = $row['country'];
            }
        }
        abort_if($unknownCountries, 422, 'Unknown CSV countries: '.implode(', ', array_slice(array_unique($unknownCountries), 0, 10)));

        return DB::transaction(function () use ($resolved): array {
            $countries = [];
            $names = [];
            foreach ($resolved as $entry) {
                $country = $entry['country'];
                $countries[$country['code']] = $country;
                $name = Str::of($entry['row']['city'])->ascii()->lower()->squish()->toString();
                $names[$name] = true;
                if (isset(self::CITY_ALIASES[$name])) {
                    $names[self::CITY_ALIASES[$name]] = true;
                }
            }
            $codes = array_keys($countries);
            $existingCountries = Country::whereIn('code', $codes)->pluck('code')->all();
            $newCountries = [];
            foreach (array_diff($codes, $existingCountries) as $code) {
                $country = $countries[$code];
                $newCountries[] = [
                    'code' => $code, 'name' => $country['name'],
                    'normalized_name' => Str::of($country['name'])->ascii()->lower()->squish()->toString(),
                    'continent_code' => $country['continent_code'], 'flag' => $country['flag'],
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if ($newCountries) {
                Country::insertOrIgnore($newCountries);
            }

            $matches = [];
            foreach (array_chunk(array_keys($names), 400) as $chunk) {
                City::whereIn('country_code', $codes)->whereIn('normalized_name', $chunk)
                    ->orderByDesc('population')->orderBy('id')->get(['id', 'country_code', 'normalized_name'])
                    ->each(function (City $city) use (&$matches): void {
                        $key = $city->country_code.':'.$city->normalized_name;
                        $matches[$key] ??= $city->id;
                    });
            }
            $syntheticIds = array_map(fn ($rank) => 'oxford-2026-'.$rank, array_keys($resolved));
            $synthetic = collect();
            foreach (array_chunk($syntheticIds, 400) as $chunk) {
                $synthetic = $synthetic->merge(City::whereIn('geoname_id', $chunk)->pluck('id', 'geoname_id'));
            }
            $ids = [];
            $newCities = [];
            foreach ($resolved as $rank => $entry) {
                $row = $entry['row'];
                $code = $entry['country']['code'];
                $name = Str::of($row['city'])->ascii()->lower()->squish()->toString();
                $key = $code.':'.$name;
                $alias = self::CITY_ALIASES[$name] ?? null;
                $candidateId = $matches[$key] ?? ($alias ? ($matches[$code.':'.$alias] ?? null) : null);
                $syntheticId = 'oxford-2026-'.$rank;
                if ($candidateId || isset($synthetic[$syntheticId])) {
                    $ids[$rank] = $candidateId ?? $synthetic[$syntheticId];
                    continue;
                }
                $newCities[] = [
                    'geoname_id' => $syntheticId, 'name' => $row['city'],
                    'ascii_name' => Str::ascii($row['city']), 'normalized_name' => $name,
                    'country_code' => $code, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            foreach (array_chunk($newCities, 400) as $chunk) {
                City::insertOrIgnore($chunk);
            }
            $inserted = collect();
            foreach (array_chunk(array_column($newCities, 'geoname_id'), 400) as $chunk) {
                $inserted = $inserted->merge(City::whereIn('geoname_id', $chunk)->pluck('id', 'geoname_id'));
            }
            foreach ($resolved as $rank => $entry) {
                $ids[$rank] ??= $inserted['oxford-2026-'.$rank];
            }
            ksort($ids);

            return array_values($ids);
        });
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
