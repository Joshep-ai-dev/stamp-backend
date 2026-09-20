<?php

namespace Tests\Feature\Api;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikipediaAirportLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_is_resolved_with_geonames_and_airports_use_structured_wikidata_relationships(): void
    {
        config()->set('services.geonames.username', 'test-user');
        Http::fake([
            'secure.geonames.org/*' => Http::response(['geonames' => [[
                'geonameId' => 5780026, 'name' => 'Provo', 'toponymName' => 'Provo',
                'adminName1' => 'Utah', 'countryCode' => 'US',
            ]]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => [[
                'airport' => ['value' => 'http://www.wikidata.org/entity/Q7252785'],
                'airportLabel' => ['value' => 'Provo Municipal Airport'],
                'iata' => ['value' => 'PVU'], 'icao' => ['value' => 'KPVU'],
            ]]]]),
        ]);
        Airport::create([
            'source_id' => 1,
            'icao_code' => 'LOCAL',
            'iata_code' => 'BAD',
            'name' => 'Incorrect Local Airport',
            'country_code' => 'US',
            'municipality' => 'Provo',
            'latitude' => 40.2,
            'longitude' => -111.7,
        ]);

        $this->getJson('/api/v1/catalog/airports?city=Provo&state=Utah&country=United%20States&countryCode=US')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Provo Municipal Airport')
            ->assertJsonPath('0.iataCode', 'PVU')
            ->assertJsonPath('0.icaoCode', 'KPVU');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'secure.geonames.org')
            && $request['name_equals'] === 'Provo' && $request['country'] === 'US');
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'query.wikidata.org')
            && str_contains(urldecode($request->url()), 'wdt:P931|wdt:P138'));
    }

    public function test_external_failure_does_not_return_country_wide_airports(): void
    {
        config()->set('services.geonames.username', 'test-user');
        Http::fake(['secure.geonames.org/*' => Http::response([], 503)]);

        $this->getJson('/api/v1/catalog/airports?city=Pattaya&state=Chon%20Buri&country=Thailand&countryCode=TH')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_manual_city_id_is_resolved_to_an_external_geonames_id(): void
    {
        config()->set('services.geonames.username', 'test-user');
        Country::create([
            'code' => 'US', 'name' => 'United States',
            'normalized_name' => 'united states', 'continent_code' => 'NA',
        ]);
        City::create([
            'geoname_id' => 'manual-provo', 'name' => 'Provo',
            'normalized_name' => 'provo', 'country_code' => 'US',
            'subcountry' => 'Utah', 'normalized_subcountry' => 'utah',
        ]);
        Http::fake([
            'secure.geonames.org/*' => Http::response(['geonames' => [[
                'geonameId' => 5780026, 'name' => 'Provo', 'toponymName' => 'Provo',
                'adminName1' => 'Utah', 'countryCode' => 'US',
            ]]]),
            'query.wikidata.org/*' => Http::response(['results' => ['bindings' => [[
                'airport' => ['value' => 'http://www.wikidata.org/entity/Q7252785'],
                'airportLabel' => ['value' => 'Provo Municipal Airport'],
                'iata' => ['value' => 'PVU'], 'icao' => ['value' => 'KPVU'],
            ]]]]),
        ]);

        $this->getJson('/api/v1/catalog/cities/manual-provo/airports')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.iataCode', 'PVU');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'secure.geonames.org')
            && $request['name_equals'] === 'Provo'
            && $request['country'] === 'US');
    }
}
