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

    public function test_city_distance_fallback_is_country_scoped_and_prefers_iata(): void
    {
        $this->airport('NEAR', ['latitude' => 0, 'longitude' => 0.1]);
        $this->airport('MID', ['iata_code' => 'MID', 'latitude' => 0, 'longitude' => 0.3]);
        $this->airport('FAR', ['iata_code' => 'FAR', 'latitude' => 0, 'longitude' => 0.8]);
        $this->airport('OUTSIDE', ['latitude' => 0, 'longitude' => 1]);
        $this->airport('FOREIGN', ['country_code' => 'US', 'latitude' => 0, 'longitude' => 0]);

        $results = app(AirportLookup::class)->forCity('TH', 'Any city', null, 0, 0);

        $this->assertSame(['MID', 'FAR', 'NEAR'], array_column($results, 'icaoCode'));
        $this->assertSame([], app(AirportLookup::class)->forCity('TH', 'Any city'));
    }

    public function test_normalized_name_match_prevents_distance_fallback(): void
    {
        $this->airport('EXACT', ['municipality' => 'catalog name', 'latitude' => 40, 'longitude' => 40]);
        $this->airport('NEAR', ['iata_code' => 'NER', 'latitude' => 0, 'longitude' => 0]);

        $results = app(AirportLookup::class)->forCity('TH', 'Local name', null, 0, 0, 'catalog name');

        $this->assertSame(['EXACT'], array_column($results, 'icaoCode'));
    }

    public function test_paris_finds_nearby_airports_with_different_municipality_names(): void
    {
        $this->airport('LFPG', [
            'name' => 'Charles de Gaulle Airport', 'iata_code' => 'CDG',
            'municipality' => 'Roissy-en-France', 'country_code' => 'FR',
            'latitude' => 49.0097, 'longitude' => 2.5479,
        ]);
        $this->airport('LFPO', [
            'name' => 'Paris Orly Airport', 'iata_code' => 'ORY',
            'municipality' => 'Paray-Vieille-Poste', 'country_code' => 'FR',
            'latitude' => 48.7262, 'longitude' => 2.3652,
        ]);
        $this->airport('PRIVATE', [
            'municipality' => 'Issy-les-Moulineaux', 'country_code' => 'FR',
            'latitude' => 48.8333, 'longitude' => 2.2728,
        ]);
        $this->airport('DISTANT', [
            'iata_code' => 'DST', 'municipality' => 'Distant', 'country_code' => 'FR',
            'latitude' => 47.7, 'longitude' => 2.35,
        ]);
        $this->airport('FOREIGN', [
            'iata_code' => 'FOR', 'municipality' => 'Nearby', 'country_code' => 'BE',
            'latitude' => 48.8566, 'longitude' => 2.3522,
        ]);

        $results = app(AirportLookup::class)->forCity('FR', 'Paris', null, 48.8566, 2.3522);

        $this->assertSame(['LFPO', 'LFPG', 'PRIVATE'], array_column($results, 'icaoCode'));
    }

    public function test_distance_fallback_handles_the_date_line(): void
    {
        $this->airport('CROSS', ['latitude' => 0, 'longitude' => -179.8]);

        $results = app(AirportLookup::class)->forCity('TH', 'Any city', null, 0, 179.8);

        $this->assertSame(['CROSS'], array_column($results, 'icaoCode'));
    }

    private function airport(string $code, array $attributes): void
    {
        Airport::create(array_merge([
            'source_id' => Airport::count() + 1, 'icao_code' => $code, 'name' => $code,
            'country_code' => 'TH', 'latitude' => 13.7, 'longitude' => 100.7,
        ], $attributes));
    }
}
