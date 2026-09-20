<?php

namespace Tests\Feature\Api;

use App\Models\Airport;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OurAirportsLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_endpoint_uses_saved_airport_catalog_without_external_requests(): void
    {
        Http::fake();
        $this->country('US', 'United States');
        $this->city('manual-provo', 'Provo', 'US', 'Utah');
        $this->airport('KPVU', 'PVU', 'Provo Municipal Airport', 'Provo', 'Utah', 'US');
        $this->airport('KPRX', 'PRX', 'Other Provo Airport', 'Provo', 'Arizona', 'US');
        $this->airport('KSBD', 'SBD', 'San Bernardino International Airport', 'San Bernardino', 'California', 'US');

        $this->getJson('/api/v1/catalog/cities/manual-provo/airports')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.iataCode', 'PVU');
        Http::assertNothingSent();
    }

    public function test_airport_name_identifies_served_city_when_municipality_differs(): void
    {
        $this->country('TH', 'Thailand');
        $this->city('manual-pattaya', 'Pattaya', 'TH', 'Chon Buri');
        $this->airport('VTBU', 'UTP', 'U-Tapao Rayong Pattaya International Airport', 'Rayong', 'Rayong', 'TH');
        $this->airport('VTBS', 'BKK', 'Suvarnabhumi Airport', 'Bangkok', 'Bangkok', 'TH');

        $this->getJson('/api/v1/catalog/cities/manual-pattaya/airports')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.iataCode', 'UTP');
    }

    public function test_hong_kong_matches_ourairports_municipality(): void
    {
        $this->country('HK', 'Hong Kong');
        $this->city('manual-hong-kong', 'Hong Kong', 'HK');
        $this->airport('VHHH', 'HKG', 'Hong Kong International Airport', 'Hong Kong', 'New Territories', 'HK');

        $this->getJson('/api/v1/catalog/cities/manual-hong-kong/airports')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.iataCode', 'HKG');
    }

    public function test_istanbul_matches_all_compound_ourairports_municipalities(): void
    {
        $this->country('TR', 'Türkiye');
        $this->city('manual-istanbul', 'Istanbul', 'TR', 'Istanbul');
        $this->airport('LTBA', 'ISL', 'İstanbul Atatürk Airport', 'Istanbul(Bakırköy)', 'İstanbul', 'TR');
        $this->airport('LTFJ', 'SAW', 'Istanbul Sabiha Gökçen International Airport', 'Pendik, Istanbul', 'İstanbul', 'TR');
        $this->airport('LTFM', 'IST', 'İstanbul Airport', 'İstanbul', 'İstanbul', 'TR');

        $this->getJson('/api/v1/catalog/cities/manual-istanbul/airports')
            ->assertOk()->assertJsonCount(3)
            ->assertJsonFragment(['iataCode' => 'ISL'])
            ->assertJsonFragment(['iataCode' => 'SAW'])
            ->assertJsonFragment(['iataCode' => 'IST']);
    }

    private function country(string $code, string $name): void
    {
        Country::create(['code' => $code, 'name' => $name, 'normalized_name' => strtolower($name), 'continent_code' => 'AS']);
    }

    private function city(string $id, string $name, string $country, ?string $state = null): void
    {
        City::create(['geoname_id' => $id, 'name' => $name, 'normalized_name' => strtolower($name),
            'country_code' => $country, 'subcountry' => $state, 'normalized_subcountry' => $state ? strtolower($state) : null]);
    }

    private function airport(string $icao, string $iata, string $name, string $city, string $state, string $country): void
    {
        Airport::create(['source_id' => Airport::count() + 1, 'icao_code' => $icao, 'iata_code' => $iata,
            'name' => $name, 'municipality' => $city, 'normalized_municipality' => strtolower($city),
            'city' => $city, 'normalized_city' => strtolower($city), 'state' => $state,
            'normalized_state' => strtolower($state), 'country_code' => $country, 'latitude' => 0, 'longitude' => 0]);
    }
}
