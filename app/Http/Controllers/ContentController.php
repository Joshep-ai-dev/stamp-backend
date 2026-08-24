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
        $cities = $country->cities->take(10);
        $sights = Sight::with(['country', 'city'])->where('country_code', $country->code)->where('is_featured', true)->orderBy('display_order')->take(20)->get();
        $collections = CollectionKind::with('lists.city.country')->where('is_published', true)->orderBy('display_order')->get()->filter(fn ($item) => $item->lists->contains(fn ($list) => $list->city?->country_code === $country->code))->values();
        $user = $request->user('sanctum');
        $visits = $user?->visits()->where('country_code', $country->code)->get() ?? collect();
        $completed = $user?->completions()->pluck('sight_id') ?? collect();

        return response()->json([
            'isEnriching' => false,
            'country' => ['id' => $country->code, 'code' => $country->code, 'name' => $country->name, 'officialName' => $country->name, 'flag' => $country->flag, 'continent' => $country->continent_code],
            'featuredIn' => [],
            'cities' => $cities->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'name' => $city->name, 'subcountry' => $city->subcountry]),
            'sights' => $sights->map(fn ($sight) => [...$this->sightItem($sight), 'completed' => $completed->contains($sight->id)]),
            'collections' => $collections->map(fn ($item) => $this->collectionItem($item)),
            'stats' => ['cities' => $visits->pluck('city_id')->unique()->count(), 'totalCities' => $cities->count(), 'sights' => $completed->intersect($sights->pluck('id'))->count(), 'totalSights' => $sights->count(), 'airports' => $visits->flatMap(fn ($visit) => $visit->places ?? [])->where('type', 'airport')->pluck('id')->unique()->count(), 'premiumSights' => $sights->where('is_premium', true)->count()],
            'visitedCities' => $visits->unique('city_id')->map(fn ($visit) => ['id' => (string) $visit->city_id, 'name' => $visit->city_name])->values(),
        ]);
    }

    public function countryCities(string $code): JsonResponse
    {
        $country = Country::with('cities')->findOrFail(strtoupper($code));

        return response()->json($country->cities->take(10)->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'name' => $city->name, 'subcountry' => $city->subcountry]));
    }

    public function dailyDestinations(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']])['date'] ?? null;
        $items = DailyDestination::where('is_published', true)
            ->when($date, fn ($query) => $query->where(fn ($q) => $q->whereNull('publish_date')->orWhere('publish_date', $date)))
            ->orderBy('display_order')->get()->map(fn ($item) => $this->daily($item));

        return response()->json($items);
    }

    public function collection(string $id): JsonResponse
    {
        $item = CollectionKind::with('lists.city.country')->where('is_published', true)->findOrFail($id);

        return response()->json($this->collectionItem($item));
    }

    public function sight(string $id): JsonResponse
    {
        return response()->json($this->sightItem(Sight::with(['country', 'city'])->findOrFail($id)));
    }

    public function city(string $id): JsonResponse
    {
        $city = City::with('country')->where('geoname_id', $id)->orWhere('id', $id)->firstOrFail();

        return response()->json([
            'id' => $city->geoname_id, 'name' => $city->name, 'countryId' => $city->country_code,
            'country' => $city->country->name, 'countryCode' => $city->country_code,
            'continentCode' => $city->country->continent_code, 'subcountry' => $city->subcountry,
            'sights' => Sight::with(['country', 'city'])->where('city_id', $city->id)->orderBy('display_order')->get()->map(fn ($sight) => $this->sightItem($sight)),
        ]);
    }

    public function citySights(string $id): JsonResponse
    {
        $city = City::where('geoname_id', $id)->orWhere('id', $id)->firstOrFail();

        return response()->json(Sight::with(['country', 'city'])->where('city_id', $city->id)->orderBy('display_order')->get()->map(fn ($sight) => $this->sightItem($sight)));
    }

    public function sightItem(Sight $item): array
    {
        return ['id' => $item->id, 'countryId' => $item->country_code, 'cityId' => $item->city?->geoname_id, 'name' => $item->name, 'slug' => $item->slug, 'description' => $item->description, 'category' => $item->category, 'imageUrl' => ImageUrl::public($item->image_url), 'isFeatured' => $item->is_featured, 'isPremium' => $item->is_premium, 'displayOrder' => $item->display_order];
    }

    public function collectionItem(CollectionKind $item): array
    {
        $item->loadMissing('lists.city.country');

        return ['id' => $item->id, 'title' => $item->title, 'detail' => $item->detail, 'imageUrl' => ImageUrl::public($item->image), 'places' => $item->lists->map(fn ($list) => ['id' => $list->id, 'collectionKindId' => $list->collectionkind_id, 'imageUrl' => ImageUrl::public($list->image), 'name' => $list->title, 'title' => $list->title, 'cityId' => $list->city?->geoname_id, 'city' => $list->city?->name, 'countryId' => $list->city?->country_code, 'country' => $list->city?->country?->name, 'location' => $list->location, 'detail' => $list->detail, 'content' => $list->detail, 'access' => $list->access, 'isPremium' => $list->access === 'pro']), 'isPublished' => $item->is_published, 'displayOrder' => $item->display_order, 'createdAt' => $item->created_at?->toISOString(), 'updatedAt' => $item->updated_at?->toISOString()];
    }

    public function daily(DailyDestination $item): array
    {
        return ['id' => $item->id, 'name' => $item->name, 'countryId' => $item->country_code, 'country' => $item->country, 'cityId' => $item->city_id ? (string) City::whereKey($item->city_id)->value('geoname_id') : null, 'city' => $item->city, 'imageUrl' => ImageUrl::public($item->image_url), 'icon' => $item->icon, 'content' => $item->content, 'question' => $item->question, 'options' => $item->options, 'correctAnswer' => $item->correct_answer, 'publishDate' => $item->publish_date?->format('Y-m-d') ?? '', 'isPublished' => $item->is_published, 'isPremium' => $item->is_premium, 'displayOrder' => $item->display_order, 'createdAt' => $item->created_at?->toISOString(), 'updatedAt' => $item->updated_at?->toISOString()];
    }
}
