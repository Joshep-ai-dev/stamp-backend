<?php

namespace Tests\Feature\Api;

use App\Models\CatalogVersion;
use App\Models\City;
use App\Models\Country;
use App\Models\Sight;
use App\Services\CityAliases;
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

    public function test_duplicate_city_search_uses_canonical_city_with_sights(): void
    {
        Country::create(['code' => 'AE', 'name' => 'United Arab Emirates', 'normalized_name' => 'united arab emirates', 'continent_code' => 'AS']);
        $canonical = City::create(['geoname_id' => '1784736618', 'name' => 'Dubai', 'normalized_name' => 'dubai', 'country_code' => 'AE', 'subcountry' => 'Dubayy', 'normalized_subcountry' => 'dubayy', 'latitude' => 25.2697, 'longitude' => 55.3094]);
        City::create(['geoname_id' => '292223', 'name' => 'Dubai', 'normalized_name' => 'dubai', 'country_code' => 'AE', 'subcountry' => 'Dubai', 'normalized_subcountry' => 'dubai']);
        $sight = Sight::create(['country_code' => 'AE', 'city_id' => $canonical->id, 'name' => 'Burj Khalifa', 'slug' => 'burj-khalifa', 'is_featured' => false]);

        $this->getJson('/api/v1/cities?query=dubai')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', '1784736618');
        $this->getJson('/api/v1/catalog/cities/292223')->assertOk()->assertJsonPath('id', '1784736618')->assertJsonPath('sights.0.id', $sight->id);
        $this->getJson('/api/v1/catalog/countries/AE')->assertOk()->assertJsonPath('sights.0.id', $sight->id);
        $this->getJson('/api/v1/catalog/countries/AE/cities')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', '1784736618');
        $this->getJson('/api/v1/catalog/cities/1784736618/sights')->assertOk()->assertJsonPath('0.id', $sight->id);
    }

    public function test_mexico_city_alias_uses_english_catalog_entry(): void
    {
        City::create(['geoname_id' => '1484247881', 'name' => 'Mexico City', 'normalized_name' => 'mexico city', 'country_code' => 'MX', 'subcountry' => 'Ciudad de México', 'normalized_subcountry' => 'ciudad de mexico', 'latitude' => 19.3538, 'longitude' => -99.1359]);

        $this->getJson('/api/v1/cities?query=mexico%20city')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', '3530597');
    }

    public function test_abu_dhabi_alias_uses_city_with_top_sights(): void
    {
        Country::create(['code' => 'AE', 'name' => 'United Arab Emirates', 'normalized_name' => 'united arab emirates', 'continent_code' => 'AS']);
        $canonical = City::create(['geoname_id' => '1784176710', 'name' => 'Abu Dhabi', 'normalized_name' => 'abu dhabi', 'country_code' => 'AE', 'subcountry' => 'Abū Z̧aby', 'normalized_subcountry' => 'abu zaby', 'latitude' => 24.4511, 'longitude' => 54.3969]);
        City::create(['geoname_id' => '292968', 'name' => 'Abu Dhabi', 'normalized_name' => 'abu dhabi', 'country_code' => 'AE', 'subcountry' => 'Abu Dhabi', 'normalized_subcountry' => 'abu dhabi']);
        Sight::create(['country_code' => 'AE', 'city_id' => $canonical->id, 'name' => 'Qasr Al Watan', 'slug' => 'qasr-al-watan', 'is_featured' => false]);

        $this->getJson('/api/v1/cities?query=abu%20dhabi')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', '1784176710');
        $this->getJson('/api/v1/catalog/cities/292968')->assertOk()->assertJsonPath('sights.0.cityId', '1784176710');
    }

    public function test_city_lists_sights_after_the_country_free_preview(): void
    {
        $other = City::create(['geoname_id' => 'other', 'name' => 'Other City', 'normalized_name' => 'other city', 'country_code' => 'MX', 'subcountry' => 'Other', 'normalized_subcountry' => 'other']);
        foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
            Sight::create(['country_code' => 'MX', 'city_id' => $other->id, 'name' => $name, 'slug' => strtolower($name)]);
        }
        $city = City::where('geoname_id', '3530597')->firstOrFail();
        $sight = Sight::create(['country_code' => 'MX', 'city_id' => $city->id, 'name' => 'Zocalo', 'slug' => 'zocalo']);

        $this->getJson('/api/v1/catalog/cities/3530597')->assertOk()->assertJsonPath('sights.0.id', $sight->id);
        $this->getJson('/api/v1/catalog/cities/3530597/sights')->assertOk()->assertJsonPath('0.id', $sight->id);
        $this->getJson('/api/v1/sights/'.$sight->id)->assertForbidden();
    }

    public function test_same_named_cities_in_different_regions_stay_separate(): void
    {
        City::create(['geoname_id' => 'north-town', 'name' => 'Springfield', 'normalized_name' => 'springfield', 'country_code' => 'MX', 'subcountry' => 'North', 'normalized_subcountry' => 'north']);
        City::create(['geoname_id' => 'south-town', 'name' => 'Springfield', 'normalized_name' => 'springfield', 'country_code' => 'MX', 'subcountry' => 'South', 'normalized_subcountry' => 'south']);

        $this->assertSame('north-town', CityAliases::canonicalId('north-town'));
        $this->assertSame('south-town', CityAliases::canonicalId('south-town'));
    }

    public function test_version_metadata_is_exposed(): void
    {
        CatalogVersion::create(['dataset' => 'world-cities.csv', 'version' => '2026-08-04', 'checksum' => str_repeat('a', 64), 'row_count' => 34065, 'imported_at' => '2026-08-04 03:00:00+00']);
        $this->getJson('/api/v1/catalog/version')->assertOk()->assertJsonPath('cityCount', 34065)->assertJsonPath('version', '2026-08-04');
    }

    public function test_us_state_returns_unfeatured_sights_only_from_that_state(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        $california = City::create(['geoname_id' => 'sf', 'name' => 'San Francisco', 'normalized_name' => 'san francisco', 'country_code' => 'US', 'subcountry' => 'California', 'normalized_subcountry' => 'california']);
        $nevada = City::create(['geoname_id' => 'lv', 'name' => 'Las Vegas', 'normalized_name' => 'las vegas', 'country_code' => 'US', 'subcountry' => 'Nevada', 'normalized_subcountry' => 'nevada']);
        $bridge = Sight::create(['country_code' => 'US', 'city_id' => $california->id, 'name' => 'Golden Gate Bridge', 'slug' => 'golden-gate-bridge', 'is_featured' => false]);
        $alcatraz = Sight::create(['country_code' => 'US', 'city_id' => $california->id, 'name' => 'Alcatraz', 'slug' => 'alcatraz', 'is_featured' => true]);
        Sight::create(['country_code' => 'US', 'city_id' => $nevada->id, 'name' => 'Las Vegas Strip', 'slug' => 'las-vegas-strip', 'is_featured' => true]);

        $this->getJson('/api/v1/catalog/countries/US')->assertOk()->assertJsonCount(3, 'sights');
        $this->getJson('/api/v1/catalog/countries/US/states/California')->assertOk()
            ->assertJsonCount(2, 'sights')
            ->assertJsonPath('sights.0.id', $alcatraz->id)
            ->assertJsonPath('sights.1.id', $bridge->id);
    }
}
