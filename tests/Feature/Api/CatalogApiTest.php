<?php

namespace Tests\Feature\Api;

use App\Models\CatalogVersion;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Country::create(['code' => 'MX', 'name' => 'Mexico', 'normalized_name' => 'mexico', 'continent_code' => 'NA', 'flag' => '🇲🇽']);
        City::create(['geoname_id' => '3530597', 'name' => 'Mexico City', 'normalized_name' => 'mexico city', 'country_code' => 'MX', 'subcountry' => 'Mexico City', 'normalized_subcountry' => 'mexico city']);
    }

    public function test_catalog_search_returns_contract_shape(): void
    {
        $this->getJson('/api/v1/cities?query=mex&limit=10')->assertOk()->assertExactJson([['id' => '3530597', 'name' => 'Mexico City', 'country' => 'Mexico', 'countryCode' => 'MX', 'continentCode' => 'NA', 'subcountry' => 'Mexico City']]);
    }

    public function test_city_query_requires_two_characters(): void
    {
        $this->getJson('/api/v1/cities?query=m')->assertUnprocessable()->assertJsonValidationErrors('query');
    }

    public function test_version_metadata_is_exposed(): void
    {
        CatalogVersion::create(['dataset' => 'world-cities.csv', 'version' => '2026-08-04', 'checksum' => str_repeat('a', 64), 'row_count' => 34065, 'imported_at' => '2026-08-04 03:00:00+00']);
        $this->getJson('/api/v1/catalog/version')->assertOk()->assertJsonPath('cityCount', 34065)->assertJsonPath('version', '2026-08-04');
    }
}
