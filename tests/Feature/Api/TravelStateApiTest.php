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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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
        $this->putJson('/api/v1/me/collections/wonders', ['progress' => 100])->assertOk()->assertJsonPath('status', 'completed');

        $this->getJson('/api/v1/me/travel-state')->assertOk()
            ->assertJsonPath('completedSightIds.0', 'eiffel-tower')
            ->assertJsonPath('collections.0.progress', 100)
            ->assertJsonPath('plan', 'free');
        $this->assertDatabaseCount('completions', 1);
    }

    public function test_clients_cannot_set_their_own_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/plan', ['plan' => 'pro'])->assertNotFound();
        $this->assertSame('free', $user->fresh()->plan);
    }

    public function test_guest_travel_state_merges_with_existing_account_state(): void
    {
        $user = User::factory()->create();
        $user->completions()->create(['sight_id' => 'server-sight', 'completed_at' => now()]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/sync/travel-state', [
            'completedSightIds' => ['local-sight'],
        ])->assertOk();

        $this->assertEqualsCanonicalizing(['local-sight', 'server-sight'], $response->json('completedSightIds'));

        $this->assertDatabaseCount('completions', 2);
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

    public function test_linked_collection_item_uses_the_same_completion_as_its_top_sight(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        $city = City::create(['geoname_id' => '5128581', 'name' => 'New York City', 'normalized_name' => 'new york city', 'country_code' => 'US', 'subcountry' => 'New York']);
        $sight = Sight::create(['country_code' => 'US', 'city_id' => $city->id, 'name' => 'Statue of Liberty', 'slug' => 'statue-of-liberty']);
        $kind = CollectionKind::create(['id' => 'icons', 'title' => 'World Icons', 'is_published' => true]);
        CollectionList::create(['id' => 'statue-of-liberty', 'collectionkind_id' => $kind->id, 'title' => 'Statue of Liberty', 'city_id' => $city->id, 'sight_id' => $sight->id]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/collections/icons')->assertOk()
            ->assertJsonPath('places.0.sightId', (string) $sight->id);
        $this->putJson('/api/v1/me/completions/collection-icons-statue-of-liberty', ['completed' => true])->assertOk()
            ->assertJsonPath('sightId', (string) $sight->id);

        $this->assertDatabaseHas('completions', ['user_id' => $user->id, 'sight_id' => (string) $sight->id]);
        $this->assertDatabaseMissing('completions', ['user_id' => $user->id, 'sight_id' => 'collection-icons-statue-of-liberty']);
        $this->assertSame([(string) $sight->id], collect($user->visits()->firstOrFail()->places)->pluck('id')->all());
    }

    public function test_visiting_a_city_completes_its_us_state_collection_item(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        $utahCity = City::create(['geoname_id' => '5780993', 'name' => 'Salt Lake City', 'normalized_name' => 'salt lake city', 'country_code' => 'US', 'subcountry' => 'Utah']);
        $alabamaCity = City::create(['geoname_id' => '4835797', 'name' => 'Alabaster', 'normalized_name' => 'alabaster', 'country_code' => 'US', 'subcountry' => 'Alabama']);
        $kind = CollectionKind::create(['id' => 'us-states', 'title' => 'United States Explorer', 'is_published' => true]);
        CollectionList::create(['id' => 'utah', 'collectionkind_id' => $kind->id, 'title' => 'Utah', 'city_id' => $utahCity->id]);
        CollectionList::create(['id' => 'alabama', 'collectionkind_id' => $kind->id, 'title' => 'Alabama', 'city_id' => $alabamaCity->id]);
        $user = User::factory()->create();
        $user->visits()->create([
            'city_id' => $utahCity->id,
            'city_name' => 'Salt Lake City',
            'country' => 'United States',
            'country_code' => 'US',
            'continent_code' => 'NA',
            'subcountry' => 'Utah',
            'visited_at' => '2026-09-14',
            'places' => [],
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/collections')->assertOk()
            ->assertJsonPath('0.progress', 50)
            ->assertJsonPath('0.places.0.name', 'Alabama')
            ->assertJsonPath('0.places.0.completed', false)
            ->assertJsonPath('0.places.1.name', 'Utah')
            ->assertJsonPath('0.places.1.completed', true);
    }

    public function test_profile_and_password_follow_contract(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'name' => 'Robb',
            'familyName' => 'Walker',
            'phoneNumber' => '+66 81 234 5678',
            'nationality' => 'United States',
            'dateOfBirth' => '1990-05-14',
            'address' => '12 Riverside Road',
            'city' => 'Bangkok',
            'stateProvince' => 'Bangkok',
            'postalCode' => '10110',
            'country' => 'Thailand',
            'photoUri' => null,
        ])->assertOk()
            ->assertJsonPath('name', 'Robb')
            ->assertJsonPath('familyName', 'Walker')
            ->assertJsonPath('phoneNumber', '+66 81 234 5678')
            ->assertJsonPath('nationality', 'United States')
            ->assertJsonPath('dateOfBirth', '1990-05-14')
            ->assertJsonPath('address', '12 Riverside Road')
            ->assertJsonPath('city', 'Bangkok')
            ->assertJsonPath('stateProvince', 'Bangkok')
            ->assertJsonPath('postalCode', '10110')
            ->assertJsonPath('country', 'Thailand')
            ->assertJsonMissingPath('sex')
            ->assertJsonPath('plan', 'free');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Robb',
            'family_name' => 'Walker',
            'phone_number' => '+66 81 234 5678',
            'nationality' => 'United States',
            'date_of_birth' => '1990-05-14',
            'address' => '12 Riverside Road',
            'city' => 'Bangkok',
            'state_province' => 'Bangkok',
            'postal_code' => '10110',
            'country' => 'Thailand',
        ]);
        $this->putJson('/api/v1/auth/password', ['currentPassword' => 'old-password', 'newPassword' => 'new-password'])->assertNoContent();
    }

    public function test_passport_fields_are_saved_during_code_account_creation(): void
    {
        $member = User::factory()->create(['friend_code' => 'inviting-member']);
        $grant = $this->postJson('/api/v1/invitations/validate', ['code' => $member->friend_code])->assertOk()->json('accessToken');
        $this->withHeader('X-Kroo-Invitation', $grant);
        $email = 'new-traveller@example.com';
        Cache::put('auth-code:'.hash('sha256', $email).':create-account', [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(10));

        $this->postJson('/api/v1/auth/code/verify', [
            'email' => $email,
            'code' => '123456',
            'purpose' => 'create-account',
            'name' => 'Robb',
            'familyName' => 'Walker',
            'phoneNumber' => '+66 81 234 5678',
            'nationality' => 'United States',
            'dateOfBirth' => '1990-05-14',
            'address' => '12 Riverside Road',
            'city' => 'Bangkok',
            'stateProvince' => 'Bangkok',
            'postalCode' => '10110',
            'country' => 'Thailand',
        ])->assertOk()
            ->assertJsonPath('user.familyName', 'Walker')
            ->assertJsonPath('user.phoneNumber', '+66 81 234 5678')
            ->assertJsonPath('user.stateProvince', 'Bangkok')
            ->assertJsonPath('user.country', 'Thailand');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'family_name' => 'Walker',
            'phone_number' => '+66 81 234 5678',
            'address' => '12 Riverside Road',
            'city' => 'Bangkok',
            'state_province' => 'Bangkok',
            'postal_code' => '10110',
            'country' => 'Thailand',
        ]);
    }
}
