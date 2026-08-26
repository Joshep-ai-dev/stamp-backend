<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitSyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_and_cloud_visits_are_merged_idempotently(): void
    {
        $user = User::factory()->create();
        Country::create(['code' => 'MY', 'name' => 'Malaysia', 'normalized_name' => 'malaysia', 'continent_code' => 'AS', 'flag' => '🇲🇾']);
        $city = City::create(['geoname_id' => '1734934', 'name' => 'Cameron Highlands', 'normalized_name' => 'cameron highlands', 'country_code' => 'MY', 'subcountry' => 'Pahang', 'normalized_subcountry' => 'pahang']);
        $payload = ['visits' => [[
            'cityId' => $city->geoname_id,
            'visitedAt' => '2026-08-26',
            'note' => 'Saved offline',
            'places' => [],
        ]]];

        $this->actingAs($user)->postJson('/api/v1/me/sync/visits', $payload)
            ->assertOk()->assertJsonCount(1);
        $this->actingAs($user)->postJson('/api/v1/me/sync/visits', $payload)
            ->assertOk()->assertJsonCount(1);
        $this->assertDatabaseCount('visits', 1);
    }
}
