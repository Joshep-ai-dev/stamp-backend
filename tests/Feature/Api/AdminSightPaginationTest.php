<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Country;
use App\Models\Sight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSightPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        config(['services.stampo.admin_key' => 'pagination-test']);
        $this->withHeader('X-Admin-Key', 'pagination-test');
        foreach (['US', 'CA'] as $code) {
            Country::create(['code' => $code, 'name' => $code, 'normalized_name' => strtolower($code), 'continent_code' => 'NA']);
            $city = City::create(['geoname_id' => $code, 'name' => $code, 'normalized_name' => strtolower($code), 'country_code' => $code, 'subcountry' => 'Region', 'normalized_subcountry' => 'region']);
            $rows = [];
            for ($i = 1; $i <= ($code === 'US' ? 205 : 1); $i++) {
                $rows[] = ['country_code' => $code, 'city_id' => $city->id, 'name' => sprintf('Sight %03d', $i), 'slug' => strtolower($code).'-'.$i];
            }
            Sight::insert($rows);
        }
    }

    public function test_page_sizes_and_country_filter_apply_to_the_full_catalog(): void
    {
        foreach ([50, 100, 200] as $size) {
            $this->getJson("/admin/api/sights?page=1&per_page={$size}&country=US")
                ->assertOk()->assertJsonCount($size, 'data')
                ->assertJsonPath('meta.total', 205)->assertJsonPath('meta.perPage', $size)
                ->assertJsonPath('data.0.name', 'Sight 001');
        }
        $this->getJson('/admin/api/sights?page=2&per_page=200&country=US')
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.name', 'Sight 201')
            ->assertJsonPath('meta.currentPage', 2)->assertJsonPath('meta.lastPage', 2);
        $this->getJson('/admin/api/sights?page=1')->assertOk()
            ->assertJsonCount(50, 'data')->assertJsonPath('meta.total', 206)->assertJsonPath('meta.perPage', 50);
    }

    public function test_empty_and_out_of_range_pages_remain_navigable(): void
    {
        $this->getJson('/admin/api/sights?page=99&per_page=100&country=US')->assertOk()
            ->assertJsonCount(5, 'data')->assertJsonPath('meta.currentPage', 3);
        $this->getJson('/admin/api/sights?page=99&country=ZZ')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.lastPage', 1)->assertJsonPath('meta.total', 0);
    }

    public function test_search_filters_the_full_catalog_before_pagination(): void
    {
        $this->getJson('/admin/api/sights?page=4&query=sIgHt%20205&country=US')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sight 205')
            ->assertJsonPath('meta.total', 1)->assertJsonPath('meta.currentPage', 1);
        $this->getJson('/admin/api/sights?page=1&query=Sight%20205&country=CA')
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_search_matches_city_region_and_country(): void
    {
        City::where('country_code', 'US')->update(['name' => 'Boston', 'subcountry' => 'Massachusetts']);
        Country::where('code', 'US')->update(['name' => 'United States']);
        foreach (['boston', 'massachusetts', 'united states', 'us'] as $search) {
            $this->getJson('/admin/api/sights?page=1&query='.rawurlencode($search))
                ->assertOk()->assertJsonCount(50, 'data')->assertJsonPath('meta.total', 205);
        }
        $this->getJson('/admin/api/sights?page=1&query='.str_repeat('a', 201))
            ->assertUnprocessable()->assertJsonValidationErrors('query');
    }

    public function test_invalid_pagination_is_rejected_and_legacy_list_is_preserved(): void
    {
        $this->getJson('/admin/api/sights?page=0&per_page=75')->assertUnprocessable()
            ->assertJsonValidationErrors(['page', 'per_page']);
        $this->getJson('/admin/api/sights')->assertOk()->assertJsonCount(206);
    }
}
