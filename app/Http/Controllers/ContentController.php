<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\DailyDestination;
use App\Models\Sight;
use App\Services\AirportLookup;
use App\Services\ExactAirportLookup;
use App\Services\ImageUrl;
use App\Services\NearbyCatalogLookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    public function nearby(Request $request, NearbyCatalogLookup $lookup): JsonResponse
    {
        $data = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'], 'radius' => ['sometimes', 'integer', 'min:100', 'max:10000']]);

        return response()->json($lookup->find((float) $data['latitude'], (float) $data['longitude'], (int) ($data['radius'] ?? 3000)));
    }

    public function country(Request $request, string $code): JsonResponse
    {
        $country = Country::with('cities')->findOrFail(strtoupper($code));
        $sights = Sight::with(['country', 'city'])->where('country_code', $country->code)->where('is_featured', true)->orderBy('name')->take(20)->get();
        $collections = CollectionKind::with('lists.city.country')->where('is_published', true)->orderBy('title')->get()->filter(fn ($item) => $item->lists->contains(fn ($list) => $list->city?->country_code === $country->code))->values();
        $user = $request->user('sanctum');
        $visits = $user?->visits()->where('country_code', $country->code)->get() ?? collect();
        $completed = $user?->completions()->pluck('sight_id') ?? collect();
        $visitedCityIds = $visits->pluck('city_id')->map(fn ($id) => (string) $id);
        $orderedCities = $country->cities->sortBy('name')->values();
        $cities = $orderedCities->take(10)
            ->merge($orderedCities->filter(fn ($city) => $visitedCityIds->contains((string) $city->geoname_id)))
            ->unique('geoname_id')->values();

        return response()->json([
            'isEnriching' => false,
            'country' => ['id' => $country->code, 'code' => $country->code, 'name' => $country->name, 'officialName' => $country->name, 'flag' => $country->flag, 'continent' => $country->continent_code, 'coverImage' => ImageUrl::public($country->hero_image)],
            'featuredIn' => [],
            'cities' => $cities->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'name' => $city->name, 'subcountry' => $city->subcountry, 'image' => ImageUrl::public($city->image_url)]),
            'states' => $country->code === 'US'
                ? CountryState::where('country_code', 'US')->orderBy('name')->get()
                    ->map(fn ($state) => ['id' => (string) $state->id, 'name' => $state->name, 'countryId' => 'US', 'imageUrl' => ImageUrl::public($state->image_url)])
                : [],
            // Always return the ordered catalog so clients can render locked
            // previews. Access to items after the first three is enforced by
            // the app entitlement and by the individual sight endpoint.
            'sights' => $sights->values()->map(fn ($sight) => [...$this->sightItem($sight), 'completed' => $completed->contains($sight->id)]),
            'collections' => $collections->map(fn ($item) => $this->collectionItem($item)),
            'stats' => ['cities' => $visits->pluck('city_id')->unique()->count(), 'totalCities' => $cities->count(), 'sights' => $completed->intersect($sights->pluck('id'))->count(), 'totalSights' => $sights->count(), 'airports' => $visits->flatMap(fn ($visit) => $visit->places ?? [])->where('type', 'airport')->pluck('id')->unique()->count()],
            'visitedCities' => $visits->unique('city_id')->map(fn ($visit) => ['id' => (string) $visit->city_id, 'name' => $visit->city_name, 'image' => ImageUrl::public($visit->image_url)])->values(),
        ]);
    }

    public function countryCities(string $code): JsonResponse
    {
        $country = Country::with('cities')->findOrFail(strtoupper($code));

        return response()->json($country->cities->sortBy('name')->values()->take(10)->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'name' => $city->name, 'subcountry' => $city->subcountry, 'image' => ImageUrl::public($city->image_url)]));
    }

    public function countryStates(string $code): JsonResponse
    {
        $country = Country::findOrFail(strtoupper($code));
        abort_unless($country->code === 'US', 404);

        return response()->json(CountryState::where('country_code', 'US')->orderBy('name')->get()
            ->map(fn ($state) => ['id' => (string) $state->id, 'name' => $state->name, 'countryId' => 'US', 'imageUrl' => ImageUrl::public($state->image_url)]));
    }

    public function stateCities(string $code, string $state): JsonResponse
    {
        $country = Country::findOrFail(strtoupper($code));

        return response()->json(City::where('country_code', $country->code)->where('subcountry', $state)
            ->orderBy('name')->get()->unique('normalized_name')->values()
            ->map(fn ($city) => ['id' => $city->geoname_id, 'countryId' => $country->code, 'state' => $city->subcountry, 'name' => $city->name, 'image' => ImageUrl::public($city->image_url)]));
    }

    public function state(Request $request, string $code, string $state): JsonResponse
    {
        $country = Country::findOrFail(strtoupper($code));
        abort_unless($country->code === 'US', 404);
        $stateRecord = CountryState::where('country_code', 'US')
            ->where('normalized_name', Str::of($state)->ascii()->lower()->squish()->toString())
            ->first();
        $stateName = $stateRecord?->name ?? City::where('country_code', 'US')
            ->where(fn ($query) => $query->where('subcountry', $state)
                ->orWhere('normalized_subcountry', Str::of($state)->ascii()->lower()->squish()->toString()))
            ->value('subcountry');
        abort_unless($stateName, 404);
        $cities = City::where('country_code', $country->code)->where('subcountry', $stateName)->orderBy('name')->get();
        $cityIds = $cities->pluck('id');
        $sights = Sight::with(['country', 'city'])->whereIn('city_id', $cityIds)
            ->where('is_featured', true)->orderBy('name')->get();
        $user = $request->user('sanctum');
        $visits = $user?->visits()->where('country_code', 'US')->where('subcountry', $stateName)->get() ?? collect();
        $completed = $user?->completions()->pluck('sight_id') ?? collect();

        return response()->json([
            'id' => (string) ($stateRecord?->id ?? $stateName),
            'name' => $stateName,
            'imageUrl' => ImageUrl::public($stateRecord?->image_url),
            'country' => ['id' => $country->code, 'code' => $country->code, 'name' => $country->name],
            'cities' => $cities->unique('normalized_name')->values()->map(fn ($city) => ['id' => $city->geoname_id, 'name' => $city->name, 'state' => $city->subcountry, 'countryId' => $country->code, 'image' => ImageUrl::public($city->image_url)]),
            'sights' => $sights->map(fn ($sight) => [...$this->sightItem($sight), 'completed' => $completed->contains($sight->id)]),
            'stats' => [
                'cities' => $visits->pluck('city_id')->unique()->count(),
                'sights' => $completed->intersect($sights->pluck('id'))->count(),
                'airports' => $visits->flatMap(fn ($visit) => $visit->places ?? [])->where('type', 'airport')->pluck('id')->unique()->count(),
            ],
            'visitedCities' => $visits->unique('city_id')->map(fn ($visit) => ['id' => (string) $visit->city_id, 'name' => $visit->city_name, 'image' => ImageUrl::public($visit->image_url)])->values(),
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

    public function collections(): JsonResponse
    {
        $items = CollectionKind::with('lists.city.country')
            ->where('is_published', true)
            ->orderBy('display_order')
            ->orderBy('title')
            ->get();

        return response()->json($items->map(fn ($item) => $this->collectionItem($item)));
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
        $city = $this->findCatalogCity($id, ['country']);

        return response()->json([
            'id' => $city->geoname_id, 'name' => $city->name, 'countryId' => $city->country_code,
            // A city can be imported before its related country row is available.
            // Still return the city guide rather than failing with a 500 error.
            'country' => $city->country?->name ?? $city->country_code,
            'countryCode' => $city->country_code,
            'continentCode' => $city->country?->continent_code ?? '', 'subcountry' => $city->subcountry,
            'latitude' => $city->latitude, 'longitude' => $city->longitude, 'population' => $city->population, 'image' => ImageUrl::public($city->image_url),
            'sights' => $this->visibleSights($request, Sight::with(['country', 'city'])->where('city_id', $city->id)->orderBy('name')->get())->map(fn ($sight) => $this->sightItem($sight)),
        ]);
    }

    public function citySights(Request $request, string $id): JsonResponse
    {
        $city = $this->findCatalogCity($id);

        $sights = Sight::with(['country', 'city'])->where('city_id', $city->id)->orderBy('name')->get();

        return response()->json($this->visibleSights($request, $sights)->map(fn ($sight) => $this->sightItem($sight)));
    }

    public function searchAirports(Request $request, ExactAirportLookup $external, AirportLookup $local): JsonResponse
    {
        $data = $request->validate([
            'city' => ['required', 'string', 'min:2', 'max:100'],
            'country' => ['required', 'string', 'min:2', 'max:100'],
            'countryCode' => ['required', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:150'],
        ]);

        $geonamesId = $external->resolveGeoNamesId(
            $data['city'],
            $data['state'] ?? '',
            $data['countryCode'],
        );
        $results = $geonamesId ? $external->forGeoNamesId($geonamesId) : [];
        if ($results === []) {
            $results = $local->forCity($data['countryCode'], $data['city']);
        }

        return response()->json($this->airportLocation($results, $data['city'], $data['state'] ?? null, $data['countryCode']));
    }

    public function cityAirports(string $id, ExactAirportLookup $external, AirportLookup $local): JsonResponse
    {
        $city = $this->findCatalogCity($id);
        $geonamesId = ctype_digit((string) $city->geoname_id)
            ? (string) $city->geoname_id
            : $external->resolveGeoNamesId(
                $city->name,
                $city->subcountry ?? '',
                $city->country_code,
            );
        $results = $geonamesId ? $external->forGeoNamesId($geonamesId) : [];
        if ($results === []) {
            $results = $local->forCity(
                $city->country_code,
                $city->name,
                $city->ascii_name,
                normalizedName: $city->normalized_name,
            );
        }

        return response()->json($this->airportLocation($results, $city->name, $city->subcountry, $city->country_code));
    }

    private function airportLocation(array $airports, string $city, ?string $state, string $countryCode): array
    {
        return collect($airports)->map(fn (array $airport): array => array_merge($airport, [
            'city' => $airport['city'] ?? $city,
            'state' => $airport['state'] ?? $state,
            'countryCode' => $airport['countryCode'] ?? strtoupper($countryCode),
        ]))->values()->all();
    }

    public function stateAirports(string $code, string $state, AirportLookup $airports): JsonResponse
    {
        abort_unless(strtoupper($code) === 'US', 404);
        CountryState::where('country_code', 'US')->where('name', $state)->firstOrFail();

        return response()->json($airports->forState('US', $state));
    }

    private function visibleSights(Request $request, $sights)
    {
        return $request->user('sanctum')?->plan === 'pro'
            ? $sights
            : $sights->filter(fn ($sight) => ! $this->requiresKrooPlus($sight))->values();
    }

    /**
     * City IDs from the admin can be readable strings (for example, "Moab").
     * Avoid comparing those to the numeric primary key on PostgreSQL.
     */
    private function findCatalogCity(string $id, array $with = []): City
    {
        $query = City::query()->when($with, fn ($query) => $query->with($with))
            ->where('geoname_id', $id);

        if (ctype_digit($id)) {
            $query->orWhere($query->getModel()->getQualifiedKeyName(), (int) $id);
        }

        return $query->firstOrFail();
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
        return ['id' => $item->id, 'countryId' => $item->country_code, 'state' => $item->city?->subcountry, 'cityId' => $item->city?->geoname_id, 'city' => $item->city?->name, 'name' => $item->name, 'slug' => $item->slug, 'description' => $item->description, 'imageUrl' => ImageUrl::public($item->image_url), 'isFeatured' => $item->is_featured, 'displayOrder' => $item->display_order];
    }

    public function collectionItem(CollectionKind $item, bool $admin = false): array
    {
        $item->loadMissing('lists.city.country', 'lists.sight');

        $hasAccess = $admin || request()->user('sanctum')?->plan === 'pro';

        return ['access' => $item->access ?? 'free', 'id' => $item->id, 'title' => $item->title, 'detail' => $item->detail, 'imageUrl' => ImageUrl::public($item->hero_image ?: $item->image), 'heroImageUrl' => ImageUrl::public($item->hero_image ?: $item->image), 'explorerImageUrl' => ImageUrl::public($item->explorer_image ?: $item->hero_image ?: $item->image), 'places' => $item->lists->sortBy('title')->values()->map(fn ($list) => ['id' => $list->id, 'sightId' => $list->sight_id ? (string) $list->sight_id : null, 'collectionKindId' => $list->collectionkind_id, 'imageUrl' => ImageUrl::public($list->image), 'name' => $list->title, 'title' => $list->title, 'cityId' => $list->city?->geoname_id, 'city' => $list->city?->name, 'state' => $list->city?->subcountry, 'countryId' => $list->city?->country_code, 'country' => $list->city?->country?->name, 'location' => $list->location, 'detail' => ($hasAccess || ($item->access !== 'pro' && $list->access !== 'pro')) ? $list->detail : '', 'content' => ($hasAccess || ($item->access !== 'pro' && $list->access !== 'pro')) ? $list->detail : '', 'access' => $item->access === 'pro' ? 'pro' : $list->access, 'isPremium' => $item->access === 'pro' || $list->access === 'pro']), 'isPublished' => $item->is_published, 'displayOrder' => $item->display_order, 'createdAt' => $item->created_at?->toISOString(), 'updatedAt' => $item->updated_at?->toISOString()];
    }

    public function daily(DailyDestination $item): array
    {
        $city = $item->city_id ? City::find($item->city_id) : null;

        $questions = collect($item->questions ?? [])->map(fn ($question) => [
            ...$question,
            'imageUrl' => ImageUrl::public($question['imageUrl'] ?? null),
        ])->values();

        return ['id' => $item->id, 'name' => $item->name, 'lessonNumber' => $item->lesson_number, 'isPreview' => (int) $item->lesson_number === 0, 'countryId' => $item->country_code, 'country' => $item->country, 'state' => $city?->subcountry, 'cityId' => $city?->geoname_id, 'city' => $item->city, 'imageUrl' => ImageUrl::public($item->image_url), 'icon' => $item->icon, 'content' => $item->content, 'questions' => $questions, 'question' => $item->question, 'options' => $item->options, 'correctAnswer' => $item->correct_answer, 'publishDate' => $item->publish_date?->format('Y-m-d') ?? '', 'isPublished' => $item->is_published, 'isPremium' => false, 'displayOrder' => $item->display_order, 'createdAt' => $item->created_at?->toISOString(), 'updatedAt' => $item->updated_at?->toISOString()];
    }
}
