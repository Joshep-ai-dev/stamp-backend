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
        $cities = City::orderBy('country_code')->orderBy('name')->get()
            ->unique(fn (City $city) => $city->country_code.':'.$city->normalized_name)
            ->values();

        return response()->json(['countries' => Country::orderBy('name')->get()->map(fn ($x) => ['id' => $x->code, 'code' => $x->code, 'name' => $x->name]), 'cities' => $cities->map(fn ($x) => ['id' => $x->geoname_id, 'countryId' => $x->country_code, 'name' => $x->name, 'subcountry' => $x->subcountry]), 'collectionKinds' => CollectionKind::orderBy('title')->get(['id', 'title'])]);
    }

    public function upload(Request $request, ImageStorage $images): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'], 'folder' => ['required', Rule::in(['sights', 'collection', 'daily-destinations'])]]);

        return response()->json(['imageUrl' => $images->store($data['image'], $data['folder'])], 201);
    }

    public function index(string $type): JsonResponse
    {
        return response()->json(match ($type) {
            'sights' => Sight::with(['country', 'city'])->orderBy('display_order')->get()->map(fn ($x) => $this->adminSight($x)),
            'collections', 'collection-kinds' => CollectionKind::with('lists.city.country')->orderBy('display_order')->get()->map(fn ($x) => (new ContentController)->collectionItem($x)),
            'collection-lists' => CollectionList::with(['kind', 'city.country'])->orderBy('display_order')->get()->map(fn ($x) => $this->collectionList($x)),
            'daily-destinations' => DailyDestination::orderBy('display_order')->get()->map(fn ($x) => (new ContentController)->daily($x)),
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

        return response()->json($this->present($type, $this->save($request, $type, $class::findOrFail($id))));
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
        if ($type === 'sights') {
            $data = $request->validate(['name' => ['required', 'string'], 'countryId' => ['required', 'exists:countries,code'], 'cityId' => ['required', 'exists:cities,geoname_id'], 'content' => ['nullable', 'string'], 'image' => ['nullable', 'string'], 'displayOrder' => ['nullable', 'integer'], 'isFeatured' => ['boolean'], 'access' => ['sometimes', Rule::in(['free', 'pro'])], 'unlocked' => ['sometimes', 'boolean']]);
            $city = City::where('geoname_id', $data['cityId'])->where('country_code', $data['countryId'])->firstOrFail();
            $premium = isset($data['access']) ? $data['access'] === 'pro' : ! ($data['unlocked'] ?? true);
            $values = ['country_code' => $data['countryId'], 'city_id' => $city->id, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'description' => $data['content'] ?? '', 'image_url' => $data['image'] ?? $model?->image_url ?? '', 'display_order' => $data['displayOrder'] ?? 0, 'is_featured' => $data['isFeatured'] ?? true, 'is_premium' => $premium];
            $model ??= new Sight;
        } elseif (in_array($type, ['collections', 'collection-kinds'], true)) {
            $data = $request->validate(['id' => ['sometimes', 'string', Rule::unique('collectionkind')->ignore($model)], 'title' => ['required', 'string'], 'detail' => ['nullable', 'string'], 'imageUrl' => ['nullable', 'string'], 'displayOrder' => ['integer'], 'isPublished' => ['boolean']]);
            $values = ['title' => $data['title'], 'detail' => $data['detail'] ?? '', 'image' => $data['imageUrl'] ?? $model?->image ?? '', 'display_order' => $data['displayOrder'] ?? 0, 'is_published' => $data['isPublished'] ?? true];
            $model ??= new CollectionKind(['id' => $data['id'] ?? (string) Str::uuid()]);
        } elseif ($type === 'collection-lists') {
            $data = $request->validate(['id' => ['sometimes', 'string', Rule::unique('collectionlist')->ignore($model)], 'collectionKindId' => ['required', 'exists:collectionkind,id'], 'title' => ['required', 'string'], 'imageUrl' => ['nullable', 'string'], 'cityId' => ['required', 'exists:cities,geoname_id'], 'detail' => ['nullable', 'string'], 'access' => ['required', Rule::in(['free', 'pro'])], 'displayOrder' => ['integer']]);
            $city = City::with('country')->where('geoname_id', $data['cityId'])->firstOrFail();
            $values = ['collectionkind_id' => $data['collectionKindId'], 'title' => $data['title'], 'image' => $data['imageUrl'] ?? $model?->image ?? '', 'city_id' => $city->id, 'location' => $city->name.', '.$city->country->name, 'detail' => $data['detail'] ?? '', 'access' => $data['access'], 'display_order' => $data['displayOrder'] ?? 0];
            $model ??= new CollectionList(['id' => $data['id'] ?? (string) Str::uuid()]);
        } elseif ($type === 'daily-destinations') {
            $data = $request->validate(['id' => ['sometimes', 'string', Rule::unique('daily_destinations')->ignore($model)], 'name' => ['required', 'string'], 'countryId' => ['required', 'exists:countries,code'], 'cityId' => ['required', 'exists:cities,geoname_id'], 'imageUrl' => ['nullable', 'string'], 'icon' => ['nullable', 'string'], 'content' => ['required', 'string'], 'question' => ['required', 'string'], 'options' => ['required', 'array', 'min:2'], 'correctAnswer' => ['required', 'integer', 'min:0'], 'publishDate' => ['nullable', 'date_format:Y-m-d'], 'displayOrder' => ['integer'], 'isPublished' => ['boolean'], 'unlocked' => ['boolean']]);
            $city = City::with('country')->where('geoname_id', $data['cityId'])->where('country_code', $data['countryId'])->firstOrFail();
            abort_if($data['correctAnswer'] >= count($data['options']), 422, 'Correct answer index is invalid.');
            $values = ['name' => $data['name'], 'country_code' => $city->country_code, 'country' => $city->country->name, 'city_id' => $city->id, 'city' => $city->name, 'image_url' => $data['imageUrl'] ?? '', 'icon' => $data['icon'] ?? '🌍', 'content' => $data['content'], 'question' => $data['question'], 'options' => $data['options'], 'correct_answer' => $data['correctAnswer'], 'publish_date' => ($data['publishDate'] ?? '') ?: null, 'display_order' => $data['displayOrder'] ?? 0, 'is_published' => $data['isPublished'] ?? true, 'is_premium' => ! ($data['unlocked'] ?? true)];
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
            'sights' => Sight::class, 'collections', 'collection-kinds' => CollectionKind::class, 'collection-lists' => CollectionList::class, 'daily-destinations' => DailyDestination::class, default => abort(404)
        };
    }

    private function present(string $type, Model $model): array
    {
        return match ($type) {
            'sights' => $this->adminSight($model->load(['country', 'city'])), 'collections', 'collection-kinds' => (new ContentController)->collectionItem($model), 'collection-lists' => $this->collectionList($model->load(['kind', 'city.country'])), 'daily-destinations' => (new ContentController)->daily($model), default => abort(404)
        };
    }

    private function adminSight(Sight $x): array
    {
        return [...(new ContentController)->sightItem($x), 'image' => ImageUrl::public($x->image_url), 'content' => $x->description, 'country' => $x->country?->name, 'countryCode' => $x->country_code, 'city' => $x->city?->name, 'access' => $x->is_premium ? 'pro' : 'free', 'unlocked' => ! $x->is_premium];
    }

    private function collectionList(CollectionList $item): array
    {
        return ['id' => $item->id, 'collectionKindId' => $item->collectionkind_id, 'collectionKind' => $item->kind?->title, 'imageUrl' => ImageUrl::public($item->image), 'title' => $item->title, 'cityId' => $item->city?->geoname_id, 'countryId' => $item->city?->country_code, 'location' => $item->location, 'detail' => $item->detail, 'access' => $item->access, 'displayOrder' => $item->display_order];
    }

    private function modelImage(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        return $model->getAttribute('image_url') ?? $model->getAttribute('image');
    }
}
