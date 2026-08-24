<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitApiTest extends TestCase
{
    use RefreshDatabase;

    private City $city;

    protected function setUp(): void
    {
        parent::setUp();
        Country::create(['code' => 'MX', 'name' => 'Mexico', 'normalized_name' => 'mexico', 'continent_code' => 'NA', 'flag' => '🇲🇽']);
        $this->city = City::create(['geoname_id' => '3530597', 'name' => 'Mexico City', 'normalized_name' => 'mexico city', 'country_code' => 'MX', 'subcountry' => 'Mexico City', 'normalized_subcountry' => 'mexico city']);
    }

    public function test_visit_uses_server_authoritative_catalog_metadata(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/visits', ['cityId' => '3530597', 'cityName' => 'Fake', 'country' => 'Fake', 'countryCode' => 'US', 'continentCode' => 'EU', 'visitedAt' => '2026-08-04', 'note' => 'Great'])->assertCreated()->assertJsonPath('cityName', 'Mexico City')->assertJsonPath('countryCode', 'MX')->assertJsonPath('continentCode', 'NA');
    }

    public function test_user_cannot_mutate_another_users_visit(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $visit = Visit::create(['user_id' => $owner->id, 'city_id' => $this->city->id, 'city_name' => 'Mexico City', 'country' => 'Mexico', 'country_code' => 'MX', 'continent_code' => 'NA', 'subcountry' => 'Mexico City', 'visited_at' => '2026-08-04']);
        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/v1/visits/'.$visit->id)->assertNotFound();
    }

    public function test_visits_require_authentication(): void
    {
        $this->getJson('/api/v1/visits')->assertUnauthorized();
    }
}
