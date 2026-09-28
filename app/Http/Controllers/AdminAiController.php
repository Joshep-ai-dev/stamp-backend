<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\Sight;
use App\Services\AiBatchRunner;
use App\Services\AiRateLimit;
use App\Services\AiStampGenerator;
use App\Services\CountryResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $batches = collect();
        foreach ([['countries'], ['states'], ['cities'], ['sights', 'discover-sights']] as $categories) {
            $batches = $batches->concat(DB::table('ai_content_batches')->whereIn('category', $categories)->orderByDesc('id')->limit(20)->get());
        }

        return response()->json([
            'configured' => filled(config('services.openai.api_key')),
            'concurrency' => AiStampGenerator::concurrency(),
            'retryAfterSeconds' => AiRateLimit::retryAfter(),
            'batches' => $batches->sortByDesc('id')->values(),
            'sectionCounts' => $this->sectionCounts(),
            'nextBatchId' => DB::table('ai_content_batches')->where('status', 'running')->orderBy('id')->value('id'),
        ]);
    }

    public function recoverRateLimits(): JsonResponse
    {
        return response()->json(['recovered' => AiRateLimit::recoverFailedItems()]);
    }

    private function sectionCounts(): array
    {
        $batchTotals = DB::table('ai_content_batches')->selectRaw('category, SUM(total) AS total, SUM(completed) AS completed, SUM(failed) AS failed')
            ->groupBy('category')->get()->keyBy('category');
        $counts = [];
        foreach ([
            'countries' => ['countries', ['countries'], 'code', 'hero_image'],
            'states' => ['country_states', ['states'], 'id', 'image_url'],
            'cities' => ['cities', ['cities'], 'id', 'image_url'],
            'discover-sights' => ['sights', ['sights', 'discover-sights'], 'id', 'image_url'],
        ] as $section => [$table, $categories, $idColumn, $imageColumn]) {
            $query = DB::table($table)->whereExists(function ($query) use ($table, $categories, $idColumn): void {
                $query->selectRaw('1')->from('ai_content_items as items')
                    ->join('ai_content_batches as batches', 'batches.id', '=', 'items.batch_id')->whereIn('batches.category', $categories);
                if ($table === 'sights') {
                    $query->where(function ($query): void {
                        $query->where(fn ($q) => $q->where('batches.category', 'sights')->whereRaw('items.target_id = CAST(sights.id AS VARCHAR)'))
                            ->orWhere(fn ($q) => $q->where('batches.category', 'discover-sights')->whereRaw('items.target_id = CAST(sights.city_id AS VARCHAR)'));
                    });
                } else {
                    $query->whereRaw('items.target_id = CAST('.$table.'.'.$idColumn.' AS VARCHAR)');
                }
            });
            if ($section === 'states') {
                $query->where('country_code', 'US');
            }
            $hasImage = "TRIM(COALESCE({$imageColumn}, '')) <> ''";
            $hasDescription = "TRIM(COALESCE(description, '')) <> ''";
            $content = $query->selectRaw("COUNT(*) AS records, SUM(CASE WHEN {$hasImage} THEN 1 ELSE 0 END) AS images, SUM(CASE WHEN {$hasDescription} THEN 1 ELSE 0 END) AS descriptions")->first();
            $totals = ['total' => 0, 'completed' => 0, 'failed' => 0];
            foreach ($categories as $category) {
                foreach ($totals as $field => $value) {
                    $totals[$field] += (int) ($batchTotals->get($category)?->{$field} ?? 0);
                }
            }
            $counts[$section] = array_merge($totals, [
                'pending' => max(0, $totals['total'] - $totals['completed'] - $totals['failed']),
                'records' => (int) $content->records, 'images' => (int) $content->images, 'descriptions' => (int) $content->descriptions,
            ]);
        }

        return $counts;
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

    public function results(Request $request, int $id): JsonResponse
    {
        $batch = DB::table('ai_content_batches')->find($id);
        abort_unless($batch, 404);
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ]);
        $items = DB::table('ai_content_items')->where('batch_id', $id)->orderBy('id')->paginate($request->integer('per_page', 25));
        $query = match ($batch->category) {
            'countries' => Country::query(),
            'states' => CountryState::query(),
            'cities' => City::with('country'),
            'sights' => Sight::with('city.country'),
            'discover-sights' => City::with(['country', 'sights']),
        };
        $key = $batch->category === 'countries' ? 'code' : 'id';
        $models = $query->whereIn($key, $items->getCollection()->pluck('target_id'))->get()->keyBy($key);
        $content = fn ($model) => [
            'id' => $model->getKey(), 'name' => $model->name,
            'image' => $model instanceof Country ? $model->hero_image : $model->image_url,
            'description' => $model->description,
            'isFeatured' => $model instanceof Sight ? $model->is_featured : null,
        ];
        $items->setCollection($items->getCollection()->map(function ($item) use ($batch, $models, $content) {
            $model = $models->get($item->target_id);

            return [
                'id' => $item->id, 'targetId' => $item->target_id,
                'status' => $item->status, 'error' => $item->error,
                'name' => $model?->name ?? 'Deleted catalog record',
                'location' => match ($batch->category) {
                    'cities', 'discover-sights' => $model?->country?->name,
                    'sights' => $model ? implode(', ', array_filter([$model->city?->name, $model->city?->country?->name])) : null,
                    'states' => 'United States',
                    default => null,
                },
                'content' => ! $model ? [] : ($batch->category === 'discover-sights'
                    ? $model->sights->map($content)->values()->all() : [$content($model)]),
            ];
        }));

        return response()->json(['batch' => $batch, 'results' => $items]);
    }

    public function removeItem(int $id, int $itemId): Response
    {
        DB::transaction(function () use ($id, $itemId): void {
            $batch = DB::table('ai_content_batches')->where('id', $id)->lockForUpdate()->first();
            abort_unless($batch, 404);
            $item = DB::table('ai_content_items')->where('batch_id', $id)->where('id', $itemId)->lockForUpdate()->first();
            abort_unless($item, 404);
            abort_if($item->status === 'working' || ($item->status === 'queued' && $batch->status === 'running'),
                409, 'Pause the batch and wait for this item to finish before removing it.');
            $total = max(0, $batch->total - 1);
            $completed = max(0, $batch->completed - ($item->status === 'complete' ? 1 : 0));
            $failed = max(0, $batch->failed - ($item->status === 'failed' ? 1 : 0));
            DB::table('ai_content_items')->where('id', $itemId)->delete();
            DB::table('ai_content_batches')->where('id', $id)->update([
                'total' => $total, 'completed' => $completed, 'failed' => $failed,
                'status' => $completed + $failed >= $total ? 'complete' : $batch->status,
                'updated_at' => now(),
            ]);
        });

        return response()->noContent();
    }

    public function updateContent(Request $request, int $id, string $target): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['present', 'nullable', 'string', 'max:20000'],
            'image' => ['present', 'nullable', 'string', 'max:2048', function ($attribute, $value, $fail): void {
                if (filled($value) && ! preg_match('~^/images/[a-z-]+/[a-zA-Z0-9._-]+$~', $value)
                    && ! (filter_var($value, FILTER_VALIDATE_URL) && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true))) {
                    $fail('Use a saved image path or an HTTP(S) image URL.');
                }
            }],
            'isFeatured' => ['sometimes', 'boolean'],
        ]);
        $model = $this->contentModel($id, $target);
        $this->ensureContentEditable($model);
        $model->name = trim($data['name']);
        abort_if($model->name === '', 422, 'Name cannot be blank.');
        if (! $model instanceof Sight) {
            $model->normalized_name = Str::of($model->name)->ascii()->lower()->squish()->toString();
        }
        $model->description = $data['description'] ?? '';
        $model->{$model instanceof Country ? 'hero_image' : 'image_url'} = $data['image'] ?? '';
        if ($model instanceof Sight && array_key_exists('isFeatured', $data)) {
            $model->is_featured = $data['isFeatured'];
        }
        $model->save();

        return response()->json(['message' => 'Content saved.']);
    }

    public function removeContent(int $id, string $target): Response
    {
        $model = $this->contentModel($id, $target);
        $this->ensureContentEditable($model);
        if ($model instanceof Sight) {
            DB::transaction(function () use ($model): void {
                $items = DB::table('ai_content_items')->where('target_id', (string) $model->id)
                    ->whereIn('batch_id', DB::table('ai_content_batches')->where('category', 'sights')->select('id'))
                    ->lockForUpdate()->get();
                foreach ($items as $item) {
                    DB::table('ai_content_items')->where('id', $item->id)->delete();
                    $batch = DB::table('ai_content_batches')->where('id', $item->batch_id)->lockForUpdate()->first();
                    DB::table('ai_content_batches')->where('id', $batch->id)->update([
                        'total' => max(0, $batch->total - 1),
                        'completed' => max(0, $batch->completed - ($item->status === 'complete' ? 1 : 0)),
                        'failed' => max(0, $batch->failed - ($item->status === 'failed' ? 1 : 0)),
                        'updated_at' => now(),
                    ]);
                    DB::table('ai_content_batches')->where('id', $batch->id)
                        ->whereRaw('completed + failed >= total')->update(['status' => 'complete']);
                }
                $model->delete();
            });
        } else {
            $model->description = '';
            $model->{$model instanceof Country ? 'hero_image' : 'image_url'} = '';
            $model->save();
        }

        return response()->noContent();
    }

    private function contentModel(int $id, string $target): Model
    {
        $batch = DB::table('ai_content_batches')->find($id);
        abort_unless($batch, 404);
        $items = DB::table('ai_content_items')->where('batch_id', $id);
        if ($batch->category === 'discover-sights') {
            return Sight::whereIn('city_id', $items->select('target_id'))->findOrFail($target);
        }
        abort_unless($items->where('target_id', $target)->exists(), 404);

        return match ($batch->category) {
            'countries' => Country::findOrFail($target),
            'states' => CountryState::findOrFail($target),
            'cities' => City::findOrFail($target),
            'sights' => Sight::findOrFail($target),
        };
    }

    private function ensureContentEditable(Model $model): void
    {
        $category = match (true) {
            $model instanceof Country => 'countries',
            $model instanceof CountryState => 'states',
            $model instanceof City => 'cities',
            $model instanceof Sight => 'sights',
        };
        $pending = DB::table('ai_content_items as items')
            ->join('ai_content_batches as batches', 'batches.id', '=', 'items.batch_id')
            ->where(function ($query) use ($category, $model): void {
                $query->where(fn ($q) => $q->where('batches.category', $category)->where('items.target_id', (string) $model->getKey()));
                if ($model instanceof Sight) {
                    $query->orWhere(fn ($q) => $q->where('batches.category', 'discover-sights')->where('items.target_id', (string) $model->city_id));
                }
            })
            ->where(fn ($q) => $q->where('items.status', 'working')
                ->orWhere(fn ($q) => $q->where('items.status', 'queued')->where('batches.status', 'running')))
            ->exists();
        abort_if($pending, 409, 'Pause the batch and wait for this item to finish before editing or removing it.');
    }

    public function start(Request $request): JsonResponse
    {
        abort_unless(filled(config('services.openai.api_key')), 503, 'Set OPENAI_API_KEY on the server.');
        $data = $request->validate([
            'category' => ['required', Rule::in(['countries', 'states', 'cities', 'sights', 'discover-sights'])],
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
            $rankedIds = $this->cityIdsFromCsv(config('ai.city_csv'));
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

        return $this->show($batchId);
    }

    public function fillMissing(int $id): JsonResponse
    {
        $batch = DB::table('ai_content_batches')->find($id);
        abort_unless($batch, 404);
        abort_if($batch->status === 'running' || DB::table('ai_content_items')->where('batch_id', $id)->where('status', 'working')->exists(),
            409, 'Pause this batch and wait for active generation to finish first.');
        $missing = [];
        DB::table('ai_content_items')->where('batch_id', $id)->orderBy('id')->chunkById(100, function ($items) use ($batch, &$missing): void {
            $query = match ($batch->category) {
                'countries' => Country::query(), 'states' => CountryState::query(),
                'cities' => City::query(), 'sights' => Sight::query(), 'discover-sights' => City::with('sights'),
            };
            $key = $batch->category === 'countries' ? 'code' : 'id';
            $models = $query->whereIn($key, $items->pluck('target_id'))->get()->keyBy($key);
            foreach ($items as $item) {
                $model = $models->get($item->target_id);
                if (! $model) {
                    continue;
                }
                $records = $batch->category === 'discover-sights' ? $model->sights : collect([$model]);
                $needsContent = $batch->category === 'discover-sights' && $records->isEmpty();
                foreach ($records as $record) {
                    $imageField = $record instanceof Country ? 'hero_image' : 'image_url';
                    if (! $this->imageAvailable($record->{$imageField})) {
                        $record->{$imageField} = '';
                        $record->save();
                        $needsContent = true;
                    }
                    $needsContent = $needsContent || blank($record->description);
                }
                if ($needsContent) {
                    $missing[] = $item->id;
                }
            }
        });
        DB::transaction(function () use ($id, $missing): void {
            foreach (array_chunk($missing, 400) as $chunk) {
                DB::table('ai_content_items')->where('batch_id', $id)->whereIn('id', $chunk)
                    ->update(['status' => 'queued', 'error' => null, 'updated_at' => now()]);
            }
            $counts = DB::table('ai_content_items')->where('batch_id', $id)->selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status');
            DB::table('ai_content_batches')->where('id', $id)->update([
                'status' => $missing ? 'running' : 'complete', 'completed' => $counts['complete'] ?? 0,
                'failed' => $counts['failed'] ?? 0, 'updated_at' => now(),
            ]);
        });

        return $this->show($id);
    }

    private function imageAvailable(?string $image): bool
    {
        if (blank($image)) {
            return false;
        }
        $host = parse_url($image, PHP_URL_HOST);
        if ($host && ! in_array(strtolower($host), array_filter([
            'localhost', '127.0.0.1', strtolower(request()->getHost()),
            strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)),
        ]), true)) {
            return true;
        }
        $path = rawurldecode((string) parse_url($image, PHP_URL_PATH));
        if (str_contains($path, '..')) {
            return false;
        }
        $file = match (true) {
            str_starts_with($path, '/storage/images/') => storage_path('app/public/images/'.basename($path)),
            str_starts_with($path, '/images/') => public_path(ltrim($path, '/')),
            default => null,
        };

        return $file ? is_file($file) && @getimagesize($file) !== false : true;
    }

    public function process(int $id, AiBatchRunner $runner): JsonResponse
    {
        abort_unless(DB::table('ai_content_batches')->where('id', $id)->exists(), 404);
        abort_unless(filled(config('services.openai.api_key')), 503, 'Set OPENAI_API_KEY on the server.');
        if (function_exists('set_time_limit')) {
            @set_time_limit(1200);
        }
        ignore_user_abort(true);
        $processed = $runner->processNext($id);
        $data = $this->show($id)->getData(true);

        return response()->json([...$data, 'processed' => $processed, 'retryAfterSeconds' => AiRateLimit::retryAfter()]);
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
        DB::table('ai_content_batches')->where('id', $id)->whereRaw('completed >= total')
            ->update(['status' => 'complete', 'updated_at' => now()]);

        return $this->show($id);
    }

    private function cityIdsFromCsv(string $path): array
    {
        abort_unless(is_readable($path), 503, 'The project Oxford city CSV is missing or unreadable.');
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
}
