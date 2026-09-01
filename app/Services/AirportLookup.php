<?php

namespace App\Services;

use App\Models\Airport;
use Illuminate\Support\Str;

class AirportLookup
{
    public function forCity(string $countryCode, string $cityName): array
    {
        return Airport::query()->where('country_code', strtoupper($countryCode))
            ->where('normalized_city', $this->normalize($cityName))->orderBy('name')->get()
            ->map(fn (Airport $airport) => $this->item($airport))->all();
    }

    public function forState(string $countryCode, string $stateName): array
    {
        return Airport::query()->where('country_code', strtoupper($countryCode))
            ->where('normalized_state', $this->normalize($stateName))->orderBy('city')->orderBy('name')->get()
            ->map(fn (Airport $airport) => $this->item($airport))->all();
    }

    private function item(Airport $airport): array
    {
        return ['id' => $airport->iata_code ?: $airport->icao_code, 'name' => $airport->name,
            'iataCode' => $airport->iata_code ?? '', 'icaoCode' => $airport->icao_code,
            'city' => $airport->city, 'state' => $airport->state, 'countryCode' => $airport->country_code,
            'latitude' => $airport->latitude, 'longitude' => $airport->longitude];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
