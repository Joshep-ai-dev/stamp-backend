<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\Country;
use App\Models\DailyDestination;
use App\Models\Sight;
use App\Services\ImageUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function country(Request $request, string $code): JsonResponse
    {
        $country = Country::with('cities')->findOrFail(strtoupper($code));
        $cities = $country->cities->sortBy('name')->values()->take(10);
        $sights = Sight::with(['country', 'city'])->where('country_code', $country->code)->where('is_featured', true)->orderBy('name')->take(20)->get();
        $collections = CollectionKind::with('lists.city.country')->where('is_published', true)->orderBy('title')->get()->filter(fn ($item) => $item->lists->contains(fn ($list) => $list->city?->country_code === $country->code))->values();
        $user = $request->user('sanctum');
        $visits = $user?->visits()->where('country_code', $country->code)->get() ?? collect();
        $completed = $user?->completions()->pluck('sight_id') ?? collect();

        return response()->json([
            'isEnriching' => false,
            'country' => ['id' => $country->code, 'code' => $country->code, 'name' => $country->name, 'officialName' => $country->name, 'flag' => $country->flag, 'continent' => $country->continent_code],
            'featuredIn' => [],
            'cities' => $cities->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'name' => $city->name, 'subcountry' => $city->subcountry]),
            'states' => $country->cities->pluck('subcountry')->filter()->unique()->sort()->values(),
            // Always return the ordered catalog so clients can render locked
            // previews. Access to items after the first three is enforced by
            // the app entitlement and by the individual sight endpoint.
            'sights' => $sights->values()->map(fn ($sight) => [...$this->sightItem($sight), 'completed' => $completed->contains($sight->id)]),
            'collections' => $collections->map(fn ($item) => $this->collectionItem($item)),
            'stats' => ['cities' => $visits->pluck('city_id')->unique()->count(), 'totalCities' => $cities->count(), 'sights' => $completed->intersect($sights->pluck('id'))->count(), 'totalSights' => $sights->count(), 'airports' => $visits->flatMap(fn ($visit) => $visit->places ?? [])->where('type', 'airport')->pluck('id')->unique()->count()],
            'visitedCities' => $visits->unique('city_id')->map(fn ($visit) => ['id' => (string) $visit->city_id, 'name' => $visit->city_name])->values(),
        ]);
    }

    public function countryCities(string $code): JsonResponse
    {
        $country = Country::with('cities')->findOrFail(strtoupper($code));

        return response()->json($country->cities->sortBy('name')->values()->take(10)->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'name' => $city->name, 'subcountry' => $city->subcountry]));
    }

    public function countryStates(string $code): JsonResponse
    {
        $country = Country::findOrFail(strtoupper($code));
        $states = City::where('country_code', $country->code)->whereNotNull('subcountry')
            ->where('subcountry', '!=', '')->distinct()->orderBy('subcountry')->pluck('subcountry');

        return response()->json($states->map(fn ($state) => ['id' => $state, 'name' => $state, 'countryId' => $country->code]));
    }

    public function stateCities(string $code, string $state): JsonResponse
    {
        $country = Country::findOrFail(strtoupper($code));

        return response()->json(City::where('country_code', $country->code)->where('subcountry', $state)
            ->orderBy('name')->get()->unique('normalized_name')->values()
            ->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'state' => $city->subcountry, 'name' => $city->name]));
    }

    public function state(Request $request, string $code, string $state): JsonResponse
    {
        $country = Country::findOrFail(strtoupper($code));
        $cities = City::where('country_code', $country->code)->where('subcountry', $state)->orderBy('name')->get();
        abort_if($cities->isEmpty(), 404);
        $cityIds = $cities->pluck('id');
        $sights = Sight::with(['country', 'city'])->whereIn('city_id', $cityIds)->orderBy('name')->get();
        $visibleSights = $this->visibleSights($request, $sights);

        return response()->json([
            'id' => $state,
            'name' => $state,
            'country' => ['id' => $country->code, 'code' => $country->code, 'name' => $country->name],
            'cities' => $cities->unique('normalized_name')->values()->map(fn ($city) => ['id' => $city->geoname_id, 'name' => $city->name, 'state' => $city->subcountry, 'countryId' => $country->code]),
            'sights' => $visibleSights->map(fn ($sight) => $this->sightItem($sight)),
        ]);
    }

    public function dailyDestinations(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']])['date'] ?? null;
        $items = DailyDestination::where('is_published', true)
            ->when($date, fn ($query) => $query->where(fn ($q) => $q->whereNull('publish_date')->orWhere('publish_date', $date)))
            ->orderBy('name')->get()->map(fn ($item) => $this->daily($item));

        return response()->json($items);
    }

    public function collection(Request $request, string $id): JsonResponse
    {
        $item = CollectionKind::with('lists.city.country')->where('is_published', true)->findOrFail($id);

        return response()->json($this->collectionItem($item));
    }

    public function sight(Request $request, string $id): JsonResponse
    {
        $sight = Sight::with(['country', 'city'])->findOrFail($id);
        $item = $this->sightItem($sight);
        abort_if($this->requiresKrooPlus($sight) && $request->user('sanctum')?->plan !== 'pro', 403, 'Kroo+ membership is required.');

        return response()->json($item);
    }

    public function city(Request $request, string $id): JsonResponse
    {
        $city = City::with('country')->where('geoname_id', $id)->orWhere('id', $id)->firstOrFail();

        return response()->json([
            'id' => $city->geoname_id, 'name' => $city->name, 'countryId' => $city->country_code,
            'country' => $city->country->name, 'countryCode' => $city->country_code,
            'continentCode' => $city->country->continent_code, 'subcountry' => $city->subcountry,
            'sights' => $this->visibleSights($request, Sight::with(['country', 'city'])->where('city_id', $city->id)->orderBy('name')->get())->map(fn ($sight) => $this->sightItem($sight)),
        ]);
    }

    public function citySights(Request $request, string $id): JsonResponse
    {
        $city = City::where('geoname_id', $id)->orWhere('id', $id)->firstOrFail();

        $sights = Sight::with(['country', 'city'])->where('city_id', $city->id)->orderBy('name')->get();

        return response()->json($this->visibleSights($request, $sights)->map(fn ($sight) => $this->sightItem($sight)));
    }

    private function visibleSights(Request $request, $sights)
    {
        return $request->user('sanctum')?->plan === 'pro'
            ? $sights
            : $sights->filter(fn ($sight) => ! $this->requiresKrooPlus($sight))->values();
    }

    private function requiresKrooPlus(Sight $item): bool
    {
        return Sight::where('country_code', $item->country_code)
            ->where('is_featured', true)
            ->where(fn ($query) => $query->where('name', '<', $item->name)
                ->orWhere(fn ($same) => $same->where('name', $item->name)->where('id', '<', $item->id)))
            ->count() >= 3;
    }

    public function sightItem(Sight $item): array
    {
        return ['id' => $item->id, 'countryId' => $item->country_code, 'state' => $item->city?->subcountry, 'cityId' => $item->city?->geoname_id, 'name' => $item->name, 'slug' => $item->slug, 'description' => $item->description, 'imageUrl' => ImageUrl::public($item->image_url), 'isFeatured' => $item->is_featured, 'displayOrder' => $item->display_order];
    }

    public function collectionItem(CollectionKind $item): array
    {
        $item->loadMissing('lists.city.country');

        return ['id' => $item->id, 'title' => $item->title, 'detail' => $item->detail, 'imageUrl' => ImageUrl::public($item->image), 'places' => $item->lists->sortBy('title')->values()->map(fn ($list) => ['id' => $list->id, 'collectionKindId' => $list->collectionkind_id, 'imageUrl' => ImageUrl::public($list->image), 'name' => $list->title, 'title' => $list->title, 'cityId' => $list->city?->geoname_id, 'city' => $list->city?->name, 'state' => $list->city?->subcountry, 'countryId' => $list->city?->country_code, 'country' => $list->city?->country?->name, 'location' => $list->location, 'detail' => $list->detail, 'content' => $list->detail, 'access' => $list->access, 'isPremium' => $list->access === 'pro']), 'isPublished' => $item->is_published, 'displayOrder' => $item->display_order, 'createdAt' => $item->created_at?->toISOString(), 'updatedAt' => $item->updated_at?->toISOString()];
    }

    public function daily(DailyDestination $item): array
    {
        $city = $item->city_id ? City::find($item->city_id) : null;

        return ['id' => $item->id, 'name' => $item->name, 'countryId' => $item->country_code, 'country' => $item->country, 'state' => $city?->subcountry, 'cityId' => $city?->geoname_id, 'city' => $item->city, 'imageUrl' => ImageUrl::public($item->image_url), 'icon' => $item->icon, 'content' => $item->content, 'question' => $item->question, 'options' => $item->options, 'correctAnswer' => $item->correct_answer, 'publishDate' => $item->publish_date?->format('Y-m-d') ?? '', 'isPublished' => $item->is_published, 'isPremium' => false, 'displayOrder' => $item->display_order, 'createdAt' => $item->created_at?->toISOString(), 'updatedAt' => $item->updated_at?->toISOString()];
    }
}
