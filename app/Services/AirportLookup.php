<?php

namespace App\Services;

use App\Models\Airport;
use Illuminate\Support\Str;

class AirportLookup
{
    private const CITY_MUNICIPALITIES = [
        'TR:istanbul' => ['istanbul', 'arnavutkoy', 'pendik'],
        // The airport source labels the New York metropolitan airports by
        // municipality (for example New York or Newark), while the city
        // catalog calls the city New York City.
        'US:new york city' => ['new york city', 'new york', 'queens', 'newark', 'flushing'],
    ];

    private const RETIRED_PASSENGER_AIRPORTS = ['LTBA'];

    public function forCity(string $countryCode, string $cityName): array
    {
        $countryCode = strtoupper($countryCode);
        $city = $this->normalize($cityName);
        $municipalities = self::CITY_MUNICIPALITIES[$countryCode.':'.$city] ?? [$city];

        return Airport::query()->where('country_code', $countryCode)
            ->whereIn('normalized_city', $municipalities)
            ->whereNotIn('icao_code', self::RETIRED_PASSENGER_AIRPORTS)
            ->orderBy('name')->get()
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
