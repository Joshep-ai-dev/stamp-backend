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

    public function test_repeat_city_visits_are_kept_separate_and_sync_idempotently(): void
    {
        $user = User::factory()->create();
        Country::create(['code' => 'MY', 'name' => 'Malaysia', 'normalized_name' => 'malaysia', 'continent_code' => 'AS', 'flag' => '🇲🇾']);
        $city = City::create(['geoname_id' => '1734934', 'name' => 'Cameron Highlands', 'normalized_name' => 'cameron highlands', 'country_code' => 'MY', 'subcountry' => 'Pahang', 'normalized_subcountry' => 'pahang']);
        $user->visits()->create(['city_id' => $city->id, 'city_name' => $city->name, 'country' => 'Malaysia', 'country_code' => 'MY', 'continent_code' => 'AS', 'subcountry' => 'Pahang', 'visited_at' => '2026-08-25', 'places' => [['id' => 'kul', 'name' => 'KUL Airport', 'type' => 'airport']]]);
        $payload = ['visits' => [[
            'id' => 'local-cameron-return-trip',
            'cityId' => $city->geoname_id,
            'visitedAt' => '2026-08-26',
            'note' => 'Saved offline',
            'places' => [['id' => 'tea', 'name' => 'Tea Plantation', 'type' => 'sight']],
        ]]];

        $this->actingAs($user)->postJson('/api/v1/me/sync/visits', $payload)
            ->assertOk()->assertJsonCount(2);
        $this->actingAs($user)->postJson('/api/v1/me/sync/visits', $payload)
            ->assertOk()->assertJsonCount(2);
        $this->assertDatabaseCount('visits', 2);
    }
}
