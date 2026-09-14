<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\Country;
use App\Models\User;
use App\Services\UsStates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompleteUsStateCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_is_idempotent_and_visits_complete_added_states(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        $kind = CollectionKind::create(['id' => 'usa', 'title' => 'United States Explorer', 'is_published' => true]);
        foreach (UsStates::NAMES as $code => $name) {
            City::create(['geoname_id' => $code, 'name' => $name.' City', 'normalized_name' => strtolower($name).' city', 'country_code' => 'US', 'subcountry' => $name]);
        }
        $ny = City::where('subcountry', 'New York')->firstOrFail();
        CollectionList::create(['id' => 'original-ny', 'collectionkind_id' => $kind->id, 'title' => 'New York', 'city_id' => $ny->id]);
        $this->artisan('collections:complete-us-states')->assertSuccessful();
        $this->assertDatabaseCount('collectionlist', 1);
        $this->artisan('collections:complete-us-states', ['--apply' => true])->assertSuccessful();
        $this->artisan('collections:complete-us-states', ['--apply' => true])->assertSuccessful();
        $this->assertSame(50, $kind->lists()->count());
        $this->assertDatabaseHas('collectionlist', ['id' => 'original-ny']);
        $user = User::factory()->create();
        $user->visits()->create(['city_id' => $ny->id, 'city_name' => $ny->name, 'country' => 'United States', 'country_code' => 'US', 'continent_code' => 'NA', 'subcountry' => 'US-NY', 'visited_at' => '2026-09-15', 'places' => []]);
        Sanctum::actingAs($user);
        $collection = collect($this->getJson('/api/v1/collections')->assertOk()->json())->firstWhere('id', 'usa');
        $this->assertSame(2, $collection['progress']);
        $this->assertTrue(collect($collection['places'])->firstWhere('name', 'New York')['completed']);
    }

    public function test_missing_catalog_data_does_not_partially_repair_the_collection(): void
    {
        CollectionKind::create(['id' => 'usa', 'title' => 'US states']);
        $this->artisan('collections:complete-us-states', ['--apply' => true])->assertFailed();
        $this->assertDatabaseCount('collectionlist', 0);
    }
}
