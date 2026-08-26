<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\Country;
use App\Models\Reward;
use App\Models\Sight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TravelStateApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_travel_state_mutations_are_idempotent_and_user_scoped(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/completions/eiffel-tower', ['completed' => true])->assertOk();
        $this->putJson('/api/v1/me/completions/eiffel-tower', ['completed' => true])->assertOk();
        $this->putJson('/api/v1/me/wishlist/city-paris', ['saved' => true])->assertOk();
        $this->putJson('/api/v1/me/plan', ['plan' => 'pro'])->assertOk()->assertJson(['plan' => 'pro']);
        $this->putJson('/api/v1/me/collections/wonders', ['progress' => 100])->assertOk()->assertJsonPath('status', 'completed');

        $this->getJson('/api/v1/me/travel-state')->assertOk()
            ->assertJsonPath('completedSightIds.0', 'eiffel-tower')
            ->assertJsonPath('wishlistIds.0', 'city-paris')
            ->assertJsonPath('collections.0.progress', 100)
            ->assertJsonPath('plan', 'pro');
        $this->assertDatabaseCount('completions', 1);
    }

    public function test_guest_travel_state_merges_with_existing_account_state(): void
    {
        $user = User::factory()->create();
        $user->completions()->create(['sight_id' => 'server-sight', 'completed_at' => now()]);
        $user->wishlists()->create(['target_id' => 'server-list', 'saved_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/sync/travel-state', [
            'completedSightIds' => ['local-sight'],
            'wishlistIds' => ['local-list'],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(['local-sight', 'server-sight'], $response->json('completedSightIds'));
        $this->assertEqualsCanonicalizing(['local-list', 'server-list'], $response->json('wishlistIds'));

        $this->assertDatabaseCount('completions', 2);
        $this->assertDatabaseCount('wishlists', 2);
    }

    public function test_home_calculates_unique_counts_and_score(): void
    {
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        $city = City::create(['geoname_id' => '2988507', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);
        $user = User::factory()->create();
        $user->visits()->create(['city_id' => $city->id, 'city_name' => 'Paris', 'country' => 'France', 'country_code' => 'FR', 'continent_code' => 'EU', 'visited_at' => '2026-08-10', 'places' => [['id' => 'cdg', 'name' => 'CDG', 'type' => 'airport'], ['id' => 'eiffel', 'name' => 'Eiffel Tower', 'type' => 'sight']]]);
        Reward::create(['user_id' => $user->id, 'title' => 'Explorer', 'kroo_points' => .8, 'unlocked' => true]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/home')->assertOk()
            ->assertJsonPath('counts.continents', 1)
            ->assertJsonPath('counts.countries', 1)
            ->assertJsonPath('counts.cities', 1)
            ->assertJsonPath('counts.airports', 1)
            ->assertJsonPath('counts.sights', 1)
            ->assertJsonPath('score', 2.067)
            ->assertJsonPath('level', 'Wanderer');
    }

    public function test_completing_catalog_items_creates_or_updates_the_city_visit(): void
    {
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        $city = City::create(['geoname_id' => '2988507', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);
        $sight = Sight::create(['country_code' => 'FR', 'city_id' => $city->id, 'name' => 'Eiffel Tower', 'slug' => 'eiffel-tower']);
        $kind = CollectionKind::create(['id' => 'icons', 'title' => 'World Icons', 'is_published' => true]);
        CollectionList::create(['id' => 'louvre', 'collectionkind_id' => $kind->id, 'title' => 'The Louvre', 'city_id' => $city->id]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/me/completions/{$sight->id}", ['completed' => true])->assertOk();
        $this->putJson('/api/v1/me/completions/collection-icons-louvre', ['completed' => true])->assertOk();

        $visit = $user->visits()->firstOrFail();
        $this->assertSame('2988507', $visit->city->geoname_id);
        $this->assertEqualsCanonicalizing([(string) $sight->id, 'collection-icons-louvre'], collect($visit->places)->pluck('id')->all());
        $this->assertDatabaseCount('visits', 1);

        $this->putJson("/api/v1/me/completions/{$sight->id}", ['completed' => false])->assertOk();
        $this->assertSame(['collection-icons-louvre'], collect($visit->fresh()->places)->pluck('id')->all());
        $this->assertDatabaseMissing('completions', ['user_id' => $user->id, 'sight_id' => (string) $sight->id]);
    }

    public function test_cityless_collection_item_saves_without_creating_a_visit(): void
    {
        Country::create(['code' => 'NO', 'name' => 'Norway', 'normalized_name' => 'norway', 'continent_code' => 'EU']);
        $city = City::create(['geoname_id' => '3133895', 'name' => 'Tromsø', 'normalized_name' => 'tromso', 'country_code' => 'NO']);
        $kind = CollectionKind::create(['id' => 'seas', 'title' => 'Seven Seas', 'is_published' => true]);
        CollectionList::create(['id' => 'arctic-ocean', 'collectionkind_id' => $kind->id, 'title' => 'Arctic Ocean', 'city_id' => $city->id]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/completions/collection-seas-arctic-ocean', ['completed' => true])->assertOk();

        $this->assertDatabaseHas('completions', ['user_id' => $user->id, 'sight_id' => 'collection-seas-arctic-ocean']);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_profile_and_password_follow_contract(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['nationality' => 'United States', 'dateOfBirth' => '1990-05-14', 'photoUri' => null])->assertOk()
            ->assertJsonPath('dateOfBirth', '1990-05-14')
            ->assertJsonPath('plan', 'free');
        $this->putJson('/api/v1/auth/password', ['currentPassword' => 'old-password', 'newPassword' => 'new-password'])->assertNoContent();
    }
}
