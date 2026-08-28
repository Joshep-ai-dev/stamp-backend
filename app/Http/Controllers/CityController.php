<?php

namespace App\Http\Controllers;

use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class CityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'query' => ['sometimes', 'nullable', 'string', 'min:2', 'max:150'],
            'country' => ['sometimes', 'string', 'size:2'],
            'state' => ['sometimes', 'nullable', 'string', 'max:150'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Str::lower(Str::ascii(trim($data['query'] ?? '')));
        abort_if($query === '' && ! isset($data['country']), 422, 'The query field is required unless a country is selected.');
        $terms = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $limit = $data['limit'] ?? 10;
        $cities = City::with('country')
            ->when(isset($data['country']), fn ($builder) => $builder->where('country_code', strtoupper($data['country'])))
            ->when($data['state'] ?? null, fn ($builder, $state) => $builder->where('subcountry', $state))
            ->when($terms, function ($builder) use ($terms): void {
                $builder->where(function ($builder) use ($terms): void {
            foreach ($terms as $term) {
                $builder->where(function ($part) use ($term): void {
                    $like = '%'.$term.'%';
                    $part->where('normalized_name', 'like', $like)
                        ->orWhere('normalized_subcountry', 'like', $like)
                        ->orWhereRaw('LOWER(country_code) LIKE ?', [$like])
                        ->orWhereHas('country', fn ($country) => $country->where('normalized_name', 'like', $like));
                    if ($term === 'usa' || $term === 'america') $part->orWhere('country_code', 'US');
                    if ($term === 'uk' || $term === 'britain') $part->orWhere('country_code', 'GB');
                });
            }
                });
            })
            ->orderByRaw('CASE WHEN normalized_name = ? THEN 0 WHEN normalized_name LIKE ? THEN 1 ELSE 2 END', [$query, $query.'%'])->orderByDesc('population')->orderBy('name')->limit($limit * 2)->get()
            ->unique(fn (City $city) => implode('|', [$city->country_code, $city->normalized_name, $city->normalized_subcountry]))
            ->take($limit)->values();

        return CityResource::collection($cities);
    }

    public function show(string $geonameId): CityResource
    {
        return new CityResource(City::with('country')->where('geoname_id', $geonameId)->firstOrFail());
    }
}
