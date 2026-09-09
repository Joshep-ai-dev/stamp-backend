<?php

namespace App\Services;

use App\Models\Airport;
use App\Models\City;
use Illuminate\Support\Str;

class AirportLookup
{
    public function search(string $query, int $limit = 50): array
    {
        $term = $this->normalize($query);
        if ($term === '') return [];
        $like = '%' . $term . '%';
        return Airport::query()->where(function ($q) use ($like) {
            foreach (['name', 'iata_code', 'icao_code', 'municipality', 'normalized_municipality', 'city', 'normalized_city', 'state', 'normalized_state'] as $column) {
                $q->orWhere($column, 'like', $like);
            }
        })->orderBy('name')->limit(max(1, min($limit, 100)))->get()->map(fn (Airport $airport) => $this->item($airport))->values()->all();
    }
    public function forCity(string $countryCode, string $cityName, ?string $asciiName = null): array
    {
        $names = $this->names([$cityName, $asciiName]);
        if ($names === []) {
            return [];
        }

        return Airport::query()->where('country_code', strtoupper(trim($countryCode)))
            ->orderBy('name')->get()
            ->filter(fn (Airport $airport) => $this->matches($airport, $names, [
                'city', 'normalized_city', 'municipality', 'normalized_municipality',
                'state', 'normalized_state',
            ]))
            ->map(fn (Airport $airport) => $this->item($airport))->values()->all();
    }

    public function forState(string $countryCode, string $stateName): array
    {
        $countryCode = strtoupper(trim($countryCode));
        $states = $this->names([$stateName]);
        if ($states === []) {
            return [];
        }

        // Resolve legacy airports without a state through cities in that region.
        $cities = City::query()->where('country_code', $countryCode)
            ->get(['name', 'ascii_name', 'normalized_name', 'subcountry', 'normalized_subcountry']);
        $cityNames = $this->names($cities->filter(fn (City $city) => array_intersect(
            $states, $this->names([$city->subcountry, $city->normalized_subcountry]),
        ) !== [])->flatMap(fn (City $city) => [$city->name, $city->ascii_name, $city->normalized_name])->all());
        // A municipality name shared by different states cannot establish membership.
        $otherCityNames = $this->names($cities->filter(fn (City $city) => array_intersect(
            $states, $this->names([$city->subcountry, $city->normalized_subcountry]),
        ) === [])->flatMap(fn (City $city) => [$city->name, $city->ascii_name, $city->normalized_name])->all());
        $cityNames = array_diff($cityNames, $otherCityNames);

        return Airport::query()->where('country_code', $countryCode)
            ->orderBy('city')->orderBy('name')->get()
            ->filter(fn (Airport $airport) => $this->matches($airport, $states, ['state', 'normalized_state'])
                || ($this->names([$airport->state, $airport->normalized_state]) === []
                    && $this->matches($airport, $cityNames, ['city', 'normalized_city', 'municipality', 'normalized_municipality'])))
            ->map(fn (Airport $airport) => $this->item($airport))->values()->all();
    }

    private function matches(Airport $airport, array $names, array $columns): bool
    {
        foreach ($columns as $column) {
            $value = $this->normalize((string) $airport->{$column});
            if ($value !== '' && in_array($value, $names, true)) {
                return true;
            }
        }

        return false;
    }

    private function names(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->normalize((string) $value), $values,
        ), fn (string $value) => $value !== '')));
    }

    private function item(Airport $airport): array
    {
        return ['id' => $airport->iata_code ?: $airport->icao_code, 'name' => $airport->name,
            'iataCode' => $airport->iata_code ?? '', 'icaoCode' => $airport->icao_code,
            'city' => $airport->city ?: $airport->municipality, 'state' => $airport->state, 'countryCode' => $airport->country_code,
            'latitude' => $airport->latitude, 'longitude' => $airport->longitude];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
