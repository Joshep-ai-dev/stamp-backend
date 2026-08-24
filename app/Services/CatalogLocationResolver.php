<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Str;

class CatalogLocationResolver
{
    public function find(string $countryName, string $cityOrRegion): ?City
    {
        try {
            $countryCode = app(CountryResolver::class)->resolve($countryName)['code'];
        } catch (\RuntimeException) {
            return null;
        }
        $normalized = Str::of($cityOrRegion)->ascii()->lower()->squish()->toString();

        return City::with('country')->where('country_code', $countryCode)
            ->where(fn ($query) => $query->where('normalized_name', $normalized)->orWhere('normalized_subcountry', $normalized))
            ->orderByRaw('CASE WHEN normalized_name = ? THEN 0 ELSE 1 END', [$normalized])
            ->orderBy('name')->first();
    }
}
