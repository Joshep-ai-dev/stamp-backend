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
        $data = $request->validate(['query' => ['required', 'string', 'min:2', 'max:150'], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50']]);
        $query = Str::lower(Str::ascii(trim($data['query'])));
        $cities = City::with('country')->where('normalized_name', 'like', '%'.$query.'%')->orderByRaw('CASE WHEN normalized_name LIKE ? THEN 0 ELSE 1 END', [$query.'%'])->orderBy('name')->limit($data['limit'] ?? 10)->get();

        return CityResource::collection($cities);
    }

    public function show(string $geonameId): CityResource
    {
        return new CityResource(City::with('country')->where('geoname_id', $geonameId)->firstOrFail());
    }
}
