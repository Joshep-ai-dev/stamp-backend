<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\Country;
use App\Models\DailyDestination;
use App\Models\Sight;
use App\Services\ImageStorage;
use App\Services\ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        return response()->json(City::where('country_code', strtoupper($country))
            ->whereNotNull('subcountry')->where('subcountry', '!=', '')
            ->distinct()->orderBy('subcountry')->pluck('subcountry')->values());
    }

    public function upload(Request $request, ImageStorage $images): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'], 'folder' => ['required', Rule::in(['countries', 'cities', 'sights', 'collection', 'daily-destinations'])]]);

        return response()->json(['imageUrl' => $images->store($data['image'], $data['folder'])], 201);
    }

    public function index(string $type): JsonResponse
    {
        return response()->json(match ($type) {
            'countries' => Country::orderBy('name')->get()->map(fn ($x) => $this->adminCountry($x)),
            'cities' => City::with('country')->orderBy('name')->get()->map(fn ($x) => $this->adminCity($x)),
            'sights' => Sight::with(['country', 'city'])->orderBy('name')->get()->map(fn ($x) => $this->adminSight($x)),
            'collections', 'collection-kinds' => CollectionKind::with('lists.city.country')->orderBy('title')->get()->map(fn ($x) => (new ContentController)->collectionItem($x)),
            'collection-lists' => CollectionList::with(['kind', 'city.country'])->orderBy('title')->get()->map(fn ($x) => $this->collectionList($x)),
            'daily-destinations' => DailyDestination::orderBy('name')->get()->map(fn ($x) => (new ContentController)->daily($x)),
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
            ? City::where('geoname_id', $id)->orWhere('id', $id)->firstOrFail()
            : $class::findOrFail($id);

        return response()->json($this->present($type, $this->save($request, $type, $model)));
    }

    public function destroy(string $type, string $id): Response
    {
        $class = $this->classFor($type);
        $model = $class::findOrFail($id);
        $image = $this->modelImage($model);
        $model->delete();
        app(ImageStorage::class)->delete($image);

        return response()->noContent();
    }

    private function save(Request $request, string $type, ?Model $model = null): Model
    {
        $oldImage = $this->modelImage($model);
        if ($type === 'countries') {
            abort_unless($model instanceof Country, 404);
            $data = $request->validate(['heroImage' => ['nullable', 'string']]);
            $values = ['hero_image' => $data['heroImage'] ?? $model->hero_image];
        } elseif ($type === 'cities') {
            $cityIdRule = Rule::unique('cities', 'geoname_id');
            if ($model) {
                $cityIdRule->ignore($model);
            }
            $data = $request->validate([
                'id' => ['sometimes', 'string', 'max:32', $cityIdRule],
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
                'geoname_id' => $model?->geoname_id ?? ($data['id'] ?? 'admin-'.Str::uuid()),
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
            $data = $request->validate(['id' => ['sometimes', 'string', Rule::unique('collectionlist')->ignore($model)], 'collectionKindId' => ['required', 'exists:collectionkind,id'], 'title' => ['required', 'string'], 'imageUrl' => ['nullable', 'string'], 'state' => ['nullable', 'string', 'max:150'], 'cityId' => ['required', 'exists:cities,geoname_id'], 'detail' => ['nullable', 'string'], 'access' => ['required', Rule::in(['free', 'pro'])]]);
            $city = City::with('country')->where('geoname_id', $data['cityId'])->firstOrFail();
            abort_if(($data['state'] ?? null) && $city->subcountry !== $data['state'], 422, 'The selected city is not in the selected state.');
            $values = ['collectionkind_id' => $data['collectionKindId'], 'title' => $data['title'], 'image' => $data['imageUrl'] ?? $model?->image ?? '', 'city_id' => $city->id, 'location' => collect([$city->name, $city->subcountry, $city->country->name])->filter()->join(', '), 'detail' => $data['detail'] ?? '', 'access' => $data['access'], 'display_order' => 0];
            $model ??= new CollectionList(['id' => $data['id'] ?? (string) Str::uuid()]);
        } elseif ($type === 'daily-destinations') {
            $data = $request->validate(['id' => ['sometimes', 'string', Rule::unique('daily_destinations')->ignore($model)], 'name' => ['required', 'string'], 'countryId' => ['required', 'exists:countries,code'], 'state' => ['nullable', 'string', 'max:150'], 'cityId' => ['required', 'exists:cities,geoname_id'], 'imageUrl' => ['nullable', 'string'], 'icon' => ['nullable', 'string'], 'content' => ['required', 'string'], 'question' => ['required', 'string'], 'options' => ['required', 'array', 'min:2'], 'correctAnswer' => ['required', 'integer', 'min:0'], 'publishDate' => ['nullable', 'date_format:Y-m-d'], 'isPublished' => ['boolean']]);
            $city = City::with('country')->where('geoname_id', $data['cityId'])->where('country_code', $data['countryId'])->firstOrFail();
            abort_if(($data['state'] ?? null) && $city->subcountry !== $data['state'], 422, 'The selected city is not in the selected state.');
            abort_if($data['correctAnswer'] >= count($data['options']), 422, 'Correct answer index is invalid.');
            $values = ['name' => $data['name'], 'country_code' => $city->country_code, 'country' => $city->country->name, 'city_id' => $city->id, 'city' => $city->name, 'image_url' => $data['imageUrl'] ?? $model?->image_url ?? '', 'icon' => $data['icon'] ?? '🌍', 'content' => $data['content'], 'question' => $data['question'], 'options' => $data['options'], 'correct_answer' => $data['correctAnswer'], 'publish_date' => ($data['publishDate'] ?? '') ?: null, 'display_order' => 0, 'is_published' => $data['isPublished'] ?? true, 'is_premium' => false];
            $model ??= new DailyDestination(['id' => $data['id'] ?? (string) Str::uuid()]);
        } else {
            abort(404);
        }
        $model->fill($values)->save();
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

    private function present(string $type, Model $model): array
    {
        return match ($type) {
            'countries' => $this->adminCountry($model), 'cities' => $this->adminCity($model->load('country')), 'sights' => $this->adminSight($model->load(['country', 'city'])), 'collections', 'collection-kinds' => (new ContentController)->collectionItem($model), 'collection-lists' => $this->collectionList($model->load(['kind', 'city.country'])), 'daily-destinations' => (new ContentController)->daily($model), default => abort(404)
        };
    }

    private function adminCountry(Country $country): array
    {
        return ['id' => $country->code, 'code' => $country->code, 'name' => $country->name, 'heroImage' => ImageUrl::public($country->hero_image)];
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
        return ['id' => $item->id, 'collectionKindId' => $item->collectionkind_id, 'collectionKind' => $item->kind?->title, 'imageUrl' => ImageUrl::public($item->image), 'title' => $item->title, 'cityId' => $item->city?->geoname_id, 'countryId' => $item->city?->country_code, 'state' => $item->city?->subcountry, 'location' => $item->location, 'detail' => $item->detail, 'access' => $item->access, 'displayOrder' => $item->display_order];
    }

    private function modelImage(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        return $model->getAttribute('hero_image') ?? $model->getAttribute('image_url') ?? $model->getAttribute('image');
    }
}
