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

    public function test_visit_only_requires_catalog_city_id_and_date(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/visits', ['cityId' => '3530597', 'visitedAt' => '2026-08-04'])
            ->assertCreated()
            ->assertJsonPath('cityName', 'Mexico City')
            ->assertJsonPath('countryCode', 'MX');
    }

    public function test_user_cannot_mutate_another_users_visit(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $visit = Visit::create(['user_id' => $owner->id, 'city_id' => $this->city->id, 'city_name' => 'Mexico City', 'country' => 'Mexico', 'country_code' => 'MX', 'continent_code' => 'NA', 'subcountry' => 'Mexico City', 'visited_at' => '2026-08-04']);
        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/v1/visits/'.$visit->id)->assertNotFound();
    }

    public function test_user_cannot_add_the_same_city_twice(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $payload = ['cityId' => '3530597', 'visitedAt' => '2026-08-04'];

        $this->postJson('/api/v1/visits', $payload)->assertCreated();
        $this->postJson('/api/v1/visits', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cityId')
            ->assertJsonPath('errors.cityId.0', 'You have already added this city to your visits.');

        $this->assertDatabaseCount('visits', 1);
    }

    public function test_visits_require_authentication(): void
    {
        $this->getJson('/api/v1/visits')->assertUnauthorized();
    }
}
