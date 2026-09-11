<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\DailyDestination;
use App\Models\Sight;
use App\Services\ImageStorage;
use App\Services\ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'countries' => Country::orderBy('name')->get(['code', 'name'])->map(fn ($x) => ['id' => $x->code, 'code' => $x->code, 'name' => $x->name]),
            'collectionKinds' => CollectionKind::orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country' => ['sometimes', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:150'],
            'query' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:100'],
        ]);
        if (! isset($data['country'])) {
            $search = Str::of($data['query'] ?? '')->ascii()->lower()->squish()->toString();
            $cities = City::query()->with('country')
                ->when($search !== '', function ($query) use ($search): void {
                    $like = '%'.$search.'%';
                    $query->where(function ($query) use ($like): void {
                        $query->where('normalized_name', 'like', $like)
                            ->orWhere('normalized_subcountry', 'like', $like)
                            ->orWhere('country_code', 'like', strtoupper($like))
                            ->orWhereHas('country', fn ($country) => $country->where('normalized_name', 'like', $like));
                    });
                })
                ->orderBy('country_code')->orderBy('normalized_name')
                ->paginate($data['per_page'] ?? 50);

            return response()->json([
                'data' => $cities->getCollection()->map(fn ($city) => $this->adminCity($city)),
                'meta' => [
                    'currentPage' => $cities->currentPage(),
                    'lastPage' => $cities->lastPage(),
                    'perPage' => $cities->perPage(),
                    'total' => $cities->total(),
                ],
            ]);
        }
        $country = $data['country'];
        $cities = City::where('country_code', strtoupper($country))
            ->when($data['state'] ?? null, fn ($query, $state) => $query->where('subcountry', $state))
            ->orderBy('name')
            ->get(['geoname_id', 'country_code', 'name', 'normalized_name', 'subcountry'])
            ->unique('normalized_name')
            ->values()
            ->map(fn (City $city) => [
                'id' => $city->geoname_id,
                'countryId' => $city->country_code,
                'name' => $city->name,
                'subcountry' => $city->subcountry,
            ]);

        return response()->json($cities);
    }

    public function states(Request $request): JsonResponse
    {
        $country = $request->validate(['country' => ['required', 'string', 'size:2']])['country'];

        $saved = Schema::hasTable('country_states')
            ? CountryState::where('country_code', strtoupper($country))->pluck('name')
            : collect();
        $legacy = City::where('country_code', strtoupper($country))
            ->whereNotNull('subcountry')->where('subcountry', '!=', '')->distinct()->pluck('subcountry');

        return response()->json($saved->merge($legacy)->unique()->sort()->values());
    }

    public function stateList(): JsonResponse
    {
        abort_unless(Schema::hasTable('country_states'), 503, 'Run php artisan migrate before managing states.');

        return response()->json(CountryState::where('country_code', 'US')->orderBy('name')->get()
            ->map(fn ($state) => $this->adminState($state)));
    }

    public function storeState(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('country_states'), 503, 'Run php artisan migrate before adding states.');
        $data = $request->validate([
            'countryId' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'name' => ['required', 'string', 'max:150'],
        ]);
        $name = Str::of($data['name'])->squish()->toString();
        $normalized = Str::of($name)->ascii()->lower()->toString();
        $state = CountryState::firstOrCreate(
            ['country_code' => strtoupper($data['countryId']), 'normalized_name' => $normalized],
            ['name' => $name],
        );

        return response()->json(['id' => $state->id, 'countryId' => $state->country_code, 'name' => $state->name], $state->wasRecentlyCreated ? 201 : 200);
    }

    public function updateState(Request $request, string $id): JsonResponse
    {
        $state = CountryState::where('country_code', 'US')->findOrFail($id);
        $data = $request->validate(['imageUrl' => ['nullable', 'string']]);
        $oldImage = $state->image_url;
        $state->update(['image_url' => $data['imageUrl'] ?? null]);
        if ($oldImage && $oldImage !== $state->image_url) {
            app(ImageStorage::class)->delete($oldImage);
        }

        return response()->json($this->adminState($state));
    }

    public function upload(Request $request, ImageStorage $images): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'], 'folder' => ['required', Rule::in(['countries', 'states', 'cities', 'sights', 'collection', 'daily-destinations'])]]);

        return response()->json(['imageUrl' => $images->store($data['image'], $data['folder'])], 201);
    }

    public function index(string $type): JsonResponse
    {
        if ($type === 'daily-destinations') {
            $this->ensureKrooIqSchemaIsReady();
        }

        return response()->json(match ($type) {
            'countries' => Country::orderBy('name')->get()->map(fn ($x) => $this->adminCountry($x)),
            'cities' => City::with('country')->orderBy('name')->get()->map(fn ($x) => $this->adminCity($x)),
            'sights' => Sight::query()
                ->with(['country', 'city'])
                ->join('cities', 'sights.city_id', '=', 'cities.id')
                ->select('sights.*')
                ->orderBy('sights.country_code')
                ->orderBy('cities.subcountry')
                ->orderBy('cities.name')
                ->orderBy('sights.name')
                ->get()
                ->map(fn ($x) => $this->adminSight($x)),
            'collections', 'collection-kinds' => CollectionKind::with('lists.city.country')->orderBy('title')->get()->map(fn ($x) => (new ContentController)->collectionItem($x)),
            'collection-lists' => CollectionList::with(['kinds', 'city.country'])->orderBy('title')->get()->map(fn ($x) => $this->collectionList($x)),
            'daily-destinations' => DailyDestination::orderBy('lesson_number')->orderBy('name')->get()->map(fn ($x) => (new ContentController)->daily($x)),
            default => abort(404),
        });
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $model = $this->save($request, $type);

        return response()->json($this->present($type, $model), 201);
    }

    public function update(Request $request, string $type, string $id): JsonResponse
    {
        $class = $this->classFor($type);
        $model = $type === 'cities'
            ? $this->findCity($id)
            : $class::findOrFail($id);

        return response()->json($this->present($type, $this->save($request, $type, $model)));
    }

    public function destroy(string $type, string $id): Response
    {
        $class = $this->classFor($type);
        $model = $type === 'cities'
            ? $this->findCity($id)
            : $class::findOrFail($id);
        $image = $this->modelImage($model);
        if ($model instanceof City) {
            $duplicate = City::whereKeyNot($model->getKey())
                ->where('country_code', $model->country_code)
                ->where('normalized_name', $model->normalized_name)
                ->where('normalized_subcountry', $model->normalized_subcountry)
                ->orderBy('id')
                ->first();
            $dependencies = collect([
                'visits' => DB::table('visits')->where('city_id', $model->id)->exists(),
                'sights' => DB::table('sights')->where('city_id', $model->id)->exists(),
                'collections' => DB::table('collectionlist')->where('city_id', $model->id)->exists(),
                'Kroo IQ lessons' => DB::table('daily_destinations')->where('city_id', $model->id)->exists(),
            ])->filter()->keys();
            abort_if(! $duplicate && $dependencies->isNotEmpty(), 409, 'This city is still used by: '.$dependencies->join(', ').'. Remove or reassign those records first.');
            DB::transaction(function () use ($model, $duplicate): void {
                if ($duplicate) {
                    DB::table('visits')->where('city_id', $model->id)->update(['city_id' => $duplicate->id, 'city_name' => $duplicate->name]);
                    DB::table('sights')->where('city_id', $model->id)->update(['city_id' => $duplicate->id]);
                    DB::table('collectionlist')->where('city_id', $model->id)->update(['city_id' => $duplicate->id]);
                    DB::table('daily_destinations')->where('city_id', $model->id)->update(['city_id' => $duplicate->id, 'city' => $duplicate->name]);
                }
                $model->delete();
            });
        } else {
            $model->delete();
        }
        app(ImageStorage::class)->delete($image);

        return response()->noContent();
    }

    private function save(Request $request, string $type, ?Model $model = null): Model
    {
        $oldImage = $this->modelImage($model);
        if ($type === 'countries') {
            abort_unless($model instanceof Country, 404);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'heroImage' => ['nullable', 'string'],
            ]);
            $name = Str::of($data['name'])->squish()->toString();
            $values = [
                'name' => $name,
                'normalized_name' => Str::of($name)->ascii()->lower()->toString(),
                'hero_image' => $data['heroImage'] ?? $model->hero_image,
            ];
        } elseif ($type === 'cities') {
            $cityIdRule = Rule::unique('cities', 'geoname_id');
            if ($model) {
                $cityIdRule->ignore($model);
            }
            $data = $request->validate([
                'id' => ['sometimes', 'nullable', 'string', 'max:32', $cityIdRule],
                'name' => ['required', 'string', 'max:150'],
                'countryId' => ['required', 'exists:countries,code'],
                'state' => ['nullable', 'string', 'max:150'],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'population' => ['nullable', 'integer', 'min:0'],
                'imageUrl' => ['nullable', 'string'],
            ]);
            $normalizedName = Str::of($data['name'])->ascii()->lower()->squish()->toString();
            $normalizedState = filled($data['state'] ?? null)
                ? Str::of($data['state'])->ascii()->lower()->squish()->toString()
                : null;
            $values = [
                'geoname_id' => $model?->geoname_id ?? (filled($data['id'] ?? null) ? $data['id'] : 'admin-'.Str::uuid()),
                'name' => $data['name'],
                'ascii_name' => Str::ascii($data['name']),
                'normalized_name' => $normalizedName,
                'country_code' => strtoupper($data['countryId']),
                'subcountry' => $data['state'] ?? null,
                'normalized_subcountry' => $normalizedState,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'population' => $data['population'] ?? null,
                'image_url' => $data['imageUrl'] ?? $model?->image_url,
            ];
            if (filled($data['state'] ?? null) && Schema::hasTable('country_states')) {
                CountryState::firstOrCreate(
                    ['country_code' => strtoupper($data['countryId']), 'normalized_name' => $normalizedState],
                    ['name' => $data['state']],
                );
            }
            $model ??= new City;
        } elseif ($type === 'sights') {
            $data = $request->validate(['name' => ['required', 'string'], 'countryId' => ['required', 'exists:countries,code'], 'state' => ['nullable', 'string', 'max:150'], 'cityId' => ['required', 'exists:cities,geoname_id'], 'content' => ['nullable', 'string'], 'image' => ['nullable', 'string'], 'isFeatured' => ['boolean']]);
            $city = City::where('geoname_id', $data['cityId'])->where('country_code', $data['countryId'])->firstOrFail();
            abort_if(($data['state'] ?? null) && $city->subcountry !== $data['state'], 422, 'The selected city is not in the selected state.');
            $values = ['country_code' => $data['countryId'], 'city_id' => $city->id, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'description' => $data['content'] ?? '', 'image_url' => $data['image'] ?? $model?->image_url ?? '', 'display_order' => 0, 'is_featured' => $data['isFeatured'] ?? true, 'is_premium' => false];
            $model ??= new Sight;
        } elseif (in_array($type, ['collections', 'collection-kinds'], true)) {
            $data = $request->validate(['id' => ['sometimes', 'string', Rule::unique('collectionkind')->ignore($model)], 'title' => ['required', 'string'], 'detail' => ['nullable', 'string'], 'imageUrl' => ['nullable', 'string'], 'isPublished' => ['boolean']]);
            $values = ['title' => $data['title'], 'detail' => $data['detail'] ?? '', 'image' => $data['imageUrl'] ?? $model?->image ?? '', 'display_order' => 0, 'is_published' => $data['isPublished'] ?? true];
            $model ??= new CollectionKind(['id' => $data['id'] ?? (string) Str::uuid()]);
        } elseif ($type === 'collection-lists') {
            $data = $request->validate([
                'id' => ['sometimes', 'string', Rule::unique('collectionlist')->ignore($model)],
                'collectionKindId' => ['nullable', 'exists:collectionkind,id'],
                'collectionKindIds' => ['required_without:collectionKindId', 'array', 'min:1'],
                'collectionKindIds.*' => ['string', 'exists:collectionkind,id'],
                'title' => ['required', 'string'],
                'imageUrl' => ['nullable', 'string'],
                'countryId' => ['nullable', 'exists:countries,code'],
                'state' => ['nullable', 'string', 'max:150'],
                'cityId' => ['nullable', 'exists:cities,geoname_id'],
                'location' => ['nullable', 'string', 'max:255'],
                'detail' => ['nullable', 'string'],
                'access' => ['nullable', Rule::in(['free', 'pro'])],
            ]);
            $kindIds = collect($data['collectionKindIds'] ?? [$data['collectionKindId']])->filter()->unique()->values()->all();
            $city = filled($data['cityId'] ?? null)
                ? City::with('country')->where('geoname_id', $data['cityId'])->firstOrFail()
                : null;
            abort_if($city && filled($data['countryId'] ?? null) && $city->country_code !== strtoupper($data['countryId']), 422, 'The selected city is not in the selected country.');
            abort_if($city && ($data['state'] ?? null) && $city->subcountry !== $data['state'], 422, 'The selected city is not in the selected state.');
            $location = $data['location'] ?? ($city ? collect([$city->name, $city->subcountry, $city->country->name])->filter()->join(', ') : $model?->location);
            $values = ['collectionkind_id' => $kindIds[0], 'title' => $data['title'], 'image' => $data['imageUrl'] ?? $model?->image ?? '', 'city_id' => $city?->id, 'location' => $location, 'detail' => $data['detail'] ?? '', 'access' => $data['access'] ?? $model?->access ?? 'free', 'display_order' => 0];
            $model ??= new CollectionList(['id' => $data['id'] ?? (string) Str::uuid()]);
        } elseif ($type === 'daily-destinations') {
            $this->ensureKrooIqSchemaIsReady();
            $data = $request->validate([
                'isPreview' => ['sometimes', 'boolean'],
                'countryId' => ['required', 'exists:countries,code'],
                'imageUrl' => ['required', 'string'], 'content' => ['required', 'string'],
                'questions' => ['required', 'array', 'min:5', 'max:10'],
                'questions.*.prompt' => ['required', 'string'], 'questions.*.answers' => ['required', 'array', 'min:2'],
                'questions.*.answers.*' => ['required', 'string'], 'questions.*.correctAnswer' => ['required', 'integer', 'min:0'],
                'questions.*.explanation' => ['nullable', 'string'], 'questions.*.imageUrl' => ['required', 'string'],
                'publishDate' => ['nullable', 'date_format:Y-m-d'], 'isPublished' => ['boolean'],
            ]);
            $isPreview = (bool) ($data['isPreview'] ?? ((int) $model?->lesson_number === 0));
            abort_if($isPreview && $model && (int) $model->lesson_number !== 0, 422, 'Lesson type is fixed after creation. This is a 5-question Kroo+ lesson.');
            abort_if(! $isPreview && $model && (int) $model->lesson_number === 0, 422, 'Lesson type is fixed after creation. Lesson 0 is the 10-question public preview.');
            abort_if($isPreview && count($data['questions']) !== 10, 422, 'Lesson 0 must contain exactly 10 questions.');
            abort_if(! $isPreview && count($data['questions']) !== 5, 422, 'Kroo+ lessons must contain exactly 5 questions.');
            abort_if($isPreview && ! $model && DailyDestination::where('lesson_number', 0)->exists(), 422, 'Lesson 0 already exists.');
            foreach ($data['questions'] as $question) abort_if($question['correctAnswer'] >= count($question['answers']), 422, 'A correct choice number is invalid.');
            $country = Country::findOrFail(strtoupper($data['countryId']));
            $first = $data['questions'][0];
            $lessonNumber = $model?->lesson_number ?? ($isPreview ? 0 : max(1, (int) DailyDestination::max('lesson_number') + 1));
            $city = (object) ['country_code' => $country->code, 'country' => $country, 'id' => null, 'name' => null];
            $data += ['name' => "Lesson {$lessonNumber} - {$country->name}", 'icon' => '🌍', 'question' => $first['prompt'], 'options' => $first['answers'], 'correctAnswer' => $first['correctAnswer']];
            $values = ['name' => $data['name'], 'country_code' => $city->country_code, 'country' => $city->country->name, 'city_id' => $city->id, 'city' => $city->name, 'image_url' => $data['imageUrl'] ?? $model?->image_url ?? '', 'icon' => $data['icon'] ?? '🌍', 'content' => $data['content'], 'question' => $data['question'], 'options' => $data['options'], 'correct_answer' => $data['correctAnswer'], 'publish_date' => ($data['publishDate'] ?? '') ?: null, 'display_order' => 0, 'is_published' => $data['isPublished'] ?? true, 'is_premium' => false];
            $values += ['questions' => $data['questions'], 'lesson_number' => $lessonNumber, 'display_order' => $lessonNumber];
            $values['display_order'] = $lessonNumber;
            $model ??= new DailyDestination(['id' => $data['id'] ?? (string) Str::uuid()]);
        } else {
            abort(404);
        }
        $model->fill($values)->save();
        if ($type === 'collection-lists') {
            $model->kinds()->sync($kindIds);
        }
        $newImage = $this->modelImage($model);
        if ($oldImage && $oldImage !== $newImage) {
            app(ImageStorage::class)->delete($oldImage);
        }

        return $model->fresh();
    }

    private function classFor(string $type): string
    {
        return match ($type) {
            'countries' => Country::class, 'cities' => City::class, 'sights' => Sight::class, 'collections', 'collection-kinds' => CollectionKind::class, 'collection-lists' => CollectionList::class, 'daily-destinations' => DailyDestination::class, default => abort(404)
        };
    }

    private function ensureKrooIqSchemaIsReady(): void
    {
        abort_unless(
            Schema::hasColumns('daily_destinations', ['lesson_number', 'questions']),
            503,
            'Kroo IQ is being updated. Run the latest database migrations, then try again.',
        );
    }

    private function findCity(string $id): City
    {
        $city = City::where('geoname_id', $id)->first();
        if ($city) {
            return $city;
        }

        abort_unless(ctype_digit($id), 404);

        return City::findOrFail($id);
    }

    private function present(string $type, Model $model): array
    {
        return match ($type) {
            'countries' => $this->adminCountry($model), 'cities' => $this->adminCity($model->load('country')), 'sights' => $this->adminSight($model->load(['country', 'city'])), 'collections', 'collection-kinds' => (new ContentController)->collectionItem($model), 'collection-lists' => $this->collectionList($model->load(['kinds', 'city.country'])), 'daily-destinations' => (new ContentController)->daily($model), default => abort(404)
        };
    }

    private function adminCountry(Country $country): array
    {
        return ['id' => $country->code, 'code' => $country->code, 'name' => $country->name, 'heroImage' => ImageUrl::public($country->hero_image)];
    }

    private function adminState(CountryState $state): array
    {
        return ['id' => $state->id, 'countryId' => $state->country_code, 'name' => $state->name, 'imageUrl' => ImageUrl::public($state->image_url)];
    }

    private function adminCity(City $city): array
    {
        return ['id' => $city->geoname_id, 'countryId' => $city->country_code, 'country' => $city->country?->name, 'state' => $city->subcountry, 'name' => $city->name, 'latitude' => $city->latitude, 'longitude' => $city->longitude, 'population' => $city->population, 'imageUrl' => ImageUrl::public($city->image_url)];
    }

    private function adminSight(Sight $x): array
    {
        return [...(new ContentController)->sightItem($x), 'image' => ImageUrl::public($x->image_url), 'content' => $x->description, 'country' => $x->country?->name, 'countryCode' => $x->country_code, 'state' => $x->city?->subcountry, 'city' => $x->city?->name];
    }

    private function collectionList(CollectionList $item): array
    {
        return ['id' => $item->id, 'collectionKindId' => $item->kinds->first()?->id ?? $item->collectionkind_id, 'collectionKindIds' => $item->kinds->pluck('id')->values(), 'collectionKind' => $item->kinds->pluck('title')->join(', '), 'imageUrl' => ImageUrl::public($item->image), 'title' => $item->title, 'cityId' => $item->city?->geoname_id, 'countryId' => $item->city?->country_code, 'state' => $item->city?->subcountry, 'location' => $item->location, 'detail' => $item->detail, 'access' => $item->access, 'displayOrder' => $item->display_order];
    }

    private function modelImage(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        return $model->getAttribute('hero_image') ?? $model->getAttribute('image_url') ?? $model->getAttribute('image');
    }
}
