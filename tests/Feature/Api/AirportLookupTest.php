<?php

namespace Tests\Feature\Api;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use App\Services\AirportLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirportLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_lookup_matches_each_location_field_and_keeps_country_scope(): void
    {
        foreach (['city', 'normalized_city', 'municipality', 'normalized_municipality'] as $index => $column) {
            $this->airport('T'.$index, [$column => '  Bángkok  ']);
        }
        $this->airport('OTHER', ['municipality' => 'Bangkok', 'country_code' => 'US']);
        $this->airport('PHUKET', ['city' => 'Phuket']);

        $results = app(AirportLookup::class)->forCity(' th ', 'Bangkok');

        $this->assertCount(4, $results);
        $this->assertSame(range(0, 3), array_keys($results));
        $this->assertSame([], app(AirportLookup::class)->forCity('TH', '  '));
    }

    public function test_city_ascii_alias_and_municipality_display_are_supported(): void
    {
        $this->airport('BKK', ['municipality' => 'Bangkok', 'normalized_municipality' => 'bangkok']);

        $results = app(AirportLookup::class)->forCity('TH', 'กรุงเทพมหานคร', 'Bangkok');

        $this->assertCount(1, $results);
        $this->assertSame('Bangkok', $results[0]['city']);
    }

    public function test_state_lookup_resolves_catalog_relationships_without_crossing_states_or_countries(): void
    {
        Country::create(['code' => 'TH', 'name' => 'Thailand', 'normalized_name' => 'thailand', 'continent_code' => 'AS']);
        foreach ([['Bangkok', 'Bangkok'], ['Shared', 'Bangkok'], ['Shared', 'Other']] as $index => [$name, $state]) {
            City::create(['geoname_id' => 9000 + $index, 'name' => $name, 'normalized_name' => strtolower($name),
                'country_code' => 'TH', 'subcountry' => $state, 'normalized_subcountry' => strtolower($state)]);
        }
        $this->airport('RAW', ['state' => ' Bángkok ']);
        foreach ([
            'NORMAL' => ['normalized_state' => 'bangkok'],
            'RELATED' => ['municipality' => 'Bangkok'],
            'CONFLICT' => ['city' => 'Bangkok', 'state' => 'Other'],
            'AMBIG' => ['municipality' => 'Shared'],
            'FOREIGN' => ['state' => 'Bangkok', 'country_code' => 'US'],
        ] as $code => $attributes) {
            $this->airport($code, $attributes);
        }

        $results = app(AirportLookup::class)->forState('TH', 'Bangkok');

        $this->assertSame(['NORMAL', 'RAW', 'RELATED'], collect($results)->pluck('icaoCode')->sort()->values()->all());
        $this->assertSame([], app(AirportLookup::class)->forState('TH', ' '));
    }

    public function test_metro_airports_are_found_outside_city_limits_without_state_matches(): void
    {
        foreach ([['US', 'New York City', 'EWR'], ['MX', 'Mexico City', 'NLU'], ['FR', 'Paris', 'CDG'], ['TH', 'Pattaya', 'UTP']] as [$country, $city, $code]) {
            $this->airport($code, ['iata_code' => $code, 'country_code' => $country, 'municipality' => 'Outside city']);
            $this->airport($code.'OTHER', ['country_code' => $country, 'state' => $city]);
            $results = app(AirportLookup::class)->forCity($country, $city);
            $this->assertSame([$code], array_column($results, 'iataCode'));
        }
    }

    private function airport(string $code, array $attributes): void
    {
        Airport::create(array_merge([
            'source_id' => Airport::count() + 1, 'icao_code' => $code, 'name' => $code,
            'country_code' => 'TH', 'latitude' => 13.7, 'longitude' => 100.7,
        ], $attributes));
    }
}
