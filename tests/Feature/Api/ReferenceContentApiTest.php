<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\Country;
use App\Models\CountryState;
use App\Models\Sight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferenceContentApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer '.config('services.stampo.admin_key')];
    }

    public function test_admin_key_protects_content_crud_and_public_routes_only_show_published_content(): void
    {
        $this->getJson('/admin/api/collections')->assertUnauthorized();
        $headers = $this->adminHeaders();
        $payload = ['id' => 'food-tour', 'title' => 'Food Tour', 'detail' => 'Taste the world', 'places' => [], 'isPublished' => true, 'unlocked' => true];

        $this->withHeaders($headers)->postJson('/admin/api/collections', $payload)->assertCreated()->assertJsonPath('id', 'food-tour');
        $this->getJson('/api/collections/food-tour')->assertOk()->assertJsonPath('title', 'Food Tour');
        $this->withHeaders($headers)->deleteJson('/admin/api/collections/food-tour')->assertNoContent();
    }

    public function test_editing_a_collection_kind_twice_updates_one_database_record(): void
    {
        $headers = $this->adminHeaders();
        $id = $this->withHeaders($headers)->postJson('/admin/api/collections', ['title' => 'Original', 'detail' => 'First'])
            ->assertCreated()->json('id');

        $this->withHeaders($headers)->putJson('/admin/api/collections/'.$id, ['title' => 'Edited once', 'detail' => 'Second'])->assertOk();
        $this->withHeaders($headers)->putJson('/admin/api/collections/'.$id, ['title' => 'Edited twice', 'detail' => 'Third'])->assertOk()->assertJsonPath('title', 'Edited twice');

        $this->assertSame(1, CollectionKind::whereKey($id)->count());
        $this->assertDatabaseHas('collectionkind', ['id' => $id, 'title' => 'Edited twice', 'detail' => 'Third']);
    }

    public function test_us_explorer_is_available_in_the_admin_collection_list(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/admin/api/collections')
            ->assertOk()
            ->assertJsonFragment(['id' => 'usa', 'title' => 'United States Explorer']);
    }

    public function test_country_targeted_collections_only_appear_on_their_country_page(): void
    {
        Country::create(['code' => 'TH', 'name' => 'Thailand', 'normalized_name' => 'thailand', 'continent_code' => 'AS']);
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        City::create(['geoname_id' => '1609350', 'name' => 'Bangkok', 'normalized_name' => 'bangkok', 'country_code' => 'TH']);
        City::create(['geoname_id' => '2988507', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);
        $headers = $this->adminHeaders();

        $this->withHeaders($headers)->postJson('/admin/api/collections', [
            'id' => 'thailand-temples',
            'title' => 'Temples of Thailand',
            'countryId' => 'TH',
        ])->assertCreated()
            ->assertJsonPath('countryId', 'TH')
            ->assertJsonPath('country', 'Thailand');
        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', [
            'collectionKindId' => 'thailand-temples',
            'title' => 'Wat Arun',
            'cityId' => '1609350',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson('/admin/api/collections', [
            'id' => 'unesco-sites',
            'title' => 'UNESCO World Heritage Sites',
        ])->assertCreated()->assertJsonPath('countryId', null);
        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', [
            'collectionKindId' => 'unesco-sites',
            'title' => 'Ayutthaya Historical Park',
            'cityId' => '1609350',
        ])->assertCreated();
        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', [
            'collectionKindId' => 'unesco-sites',
            'title' => 'Palace of Versailles',
            'cityId' => '2988507',
        ])->assertCreated();

        $this->getJson('/api/v1/collection-kinds')->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', 'unesco-sites');
        $this->getJson('/api/v1/catalog/countries/TH')->assertOk()
            ->assertJsonCount(2, 'collections')
            ->assertJsonFragment(['id' => 'thailand-temples'])
            ->assertJsonFragment(['id' => 'unesco-sites']);
        $this->getJson('/api/v1/catalog/countries/FR')->assertOk()
            ->assertJsonCount(1, 'collections')
            ->assertJsonPath('collections.0.id', 'unesco-sites')
            ->assertJsonMissing(['id' => 'thailand-temples']);
        $this->getJson('/api/v1/catalog/cities/1609350')->assertOk()
            ->assertJsonCount(1, 'collections')
            ->assertJsonPath('collections.0.id', 'unesco-sites');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/collections')->assertOk()
            ->assertJsonMissing(['id' => 'thailand-temples']);
    }

    public function test_country_target_can_be_cleared_when_editing_a_collection(): void
    {
        Country::create(['code' => 'TH', 'name' => 'Thailand', 'normalized_name' => 'thailand', 'continent_code' => 'AS']);
        $headers = $this->adminHeaders();
        $this->withHeaders($headers)->postJson('/admin/api/collections', [
            'id' => 'targeted',
            'title' => 'Targeted collection',
            'countryId' => 'TH',
        ])->assertCreated();

        $this->withHeaders($headers)->putJson('/admin/api/collections/targeted', [
            'title' => 'Targeted collection',
            'countryId' => null,
        ])->assertOk()->assertJsonPath('countryId', null);

        $this->assertDatabaseHas('collectionkind', ['id' => 'targeted', 'country_code' => null]);
    }

    public function test_collection_can_include_existing_us_states_and_sights_without_duplicating_list_items(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        $state = CountryState::create(['country_code' => 'US', 'name' => 'California', 'normalized_name' => 'california']);
        $city = City::create(['geoname_id' => '5368361', 'name' => 'Los Angeles', 'normalized_name' => 'los angeles', 'country_code' => 'US', 'subcountry' => 'California']);
        $sight = Sight::create(['country_code' => 'US', 'city_id' => $city->id, 'name' => 'Griffith Observatory', 'slug' => 'griffith-observatory', 'description' => 'A Los Angeles landmark.']);
        $directSight = Sight::create(['country_code' => 'US', 'city_id' => $city->id, 'name' => 'Hollywood Sign', 'slug' => 'hollywood-sign', 'description' => 'A hillside landmark.']);
        $headers = $this->adminHeaders();

        $this->withHeaders($headers)->postJson('/admin/api/collections', [
            'id' => 'usa',
            'title' => 'United States Explorer',
            'stateIds' => [(string) $state->id],
            'sightIds' => [(string) $sight->id, (string) $directSight->id],
        ])->assertCreated()
            ->assertJsonPath('stateIds.0', (string) $state->id)
            ->assertJsonPath('sightIds.0', (string) $sight->id);

        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', [
            'collectionKindId' => 'usa',
            'title' => 'Griffith Observatory',
            'cityId' => $city->geoname_id,
            'sightId' => (string) $sight->id,
        ])->assertCreated();

        $collection = collect($this->getJson('/api/v1/collection-kinds')->assertOk()->json())->firstWhere('id', 'usa');
        $this->assertCount(3, $collection['places']);
        $this->assertSame(1, collect($collection['places'])->where('sightId', (string) $sight->id)->count());
        $this->assertSame('state', collect($collection['places'])->firstWhere('stateId', (string) $state->id)['type']);
        $this->assertSame('sight', collect($collection['places'])->firstWhere('sightId', (string) $directSight->id)['type']);
        $this->getJson('/api/v1/catalog/countries/US')->assertOk()->assertJsonFragment(['id' => 'usa']);
        $this->getJson('/api/v1/catalog/countries/US/states/California')->assertOk()->assertJsonFragment(['id' => 'usa']);
        $this->getJson('/api/v1/catalog/cities/5368361')->assertOk()->assertJsonFragment(['id' => 'usa']);
    }

    public function test_collection_list_sight_link_stores_only_the_sight_reference_and_deleting_it_preserves_the_sight(): void
    {
        Country::create(['code' => 'TH', 'name' => 'Thailand', 'normalized_name' => 'thailand', 'continent_code' => 'AS']);
        $city = City::create(['geoname_id' => '1609350', 'name' => 'Bangkok', 'normalized_name' => 'bangkok', 'country_code' => 'TH']);
        $sight = Sight::create(['country_code' => 'TH', 'city_id' => $city->id, 'name' => 'Wat Arun', 'slug' => 'wat-arun', 'description' => 'Temple of Dawn', 'image_url' => '/images/sights/wat-arun.jpg']);
        $otherSight = Sight::create(['country_code' => 'TH', 'city_id' => $city->id, 'name' => 'Grand Palace', 'slug' => 'grand-palace', 'description' => 'Royal palace complex.']);
        $headers = $this->adminHeaders();
        $this->withHeaders($headers)->getJson('/admin/api/sight-options?query=Wat')->assertOk()
            ->assertJsonFragment(['id' => (string) $sight->id, 'name' => 'Wat Arun']);
        $kind = $this->withHeaders($headers)->postJson('/admin/api/collections', ['id' => 'bangkok-icons', 'title' => 'Bangkok Icons'])->assertCreated();

        $entry = $this->withHeaders($headers)->postJson('/admin/api/collection-lists', [
            'collectionKindId' => $kind->json('id'),
            'sightId' => (string) $sight->id,
        ])->assertCreated()
            ->assertJsonPath('sightId', (string) $sight->id)
            ->assertJsonPath('title', 'Wat Arun')
            ->assertJsonPath('detail', 'Temple of Dawn');

        $this->assertDatabaseHas('collectionlist', [
            'id' => $entry->json('id'),
            'sight_id' => $sight->id,
            'title' => null,
            'image' => null,
            'city_id' => null,
            'location' => null,
            'detail' => null,
        ]);
        $places = collect($this->getJson('/api/v1/collection-kinds')->assertOk()->json())
            ->firstWhere('id', $kind->json('id'))['places'];
        $this->assertSame('Wat Arun', collect($places)->firstWhere('sightId', (string) $sight->id)['name']);

        $this->withHeaders($headers)->putJson('/admin/api/collection-lists/'.$entry->json('id'), [
            'collectionKindId' => $kind->json('id'),
            'sightId' => (string) $otherSight->id,
        ])->assertOk()
            ->assertJsonPath('sightId', (string) $otherSight->id)
            ->assertJsonPath('title', 'Grand Palace');
        $this->assertDatabaseHas('collectionlist', ['id' => $entry->json('id'), 'sight_id' => $otherSight->id, 'title' => null]);

        $this->withHeaders($headers)->putJson('/admin/api/collection-lists/'.$entry->json('id'), [
            'collectionKindId' => $kind->json('id'),
            'sightId' => null,
        ])->assertOk()
            ->assertJsonPath('sightId', null)
            ->assertJsonPath('title', 'Grand Palace');
        $this->assertDatabaseHas('collectionlist', ['id' => $entry->json('id'), 'sight_id' => null, 'title' => 'Grand Palace']);

        $this->withHeaders($headers)->deleteJson('/admin/api/collection-lists/'.$entry->json('id'))->assertNoContent();
        $this->assertDatabaseMissing('collectionlist', ['id' => $entry->json('id')]);
        $this->assertDatabaseMissing('collection_kind_lists', ['collection_list_id' => $entry->json('id')]);
        $this->assertDatabaseHas('sights', ['id' => $sight->id, 'name' => 'Wat Arun']);
        $this->assertDatabaseHas('sights', ['id' => $otherSight->id, 'name' => 'Grand Palace']);
    }

    public function test_collection_kind_saves_local_image_paths_without_the_site_domain(): void
    {
        $this->withHeaders($this->adminHeaders())->postJson('/admin/api/collections', [
            'title' => 'Images',
            'heroImageUrl' => 'https://krootravel.com/images/collection/hero.jpg',
            'explorerImageUrl' => 'https://krootravel.com/images/collection/badge.png',
        ])->assertCreated();

        $kind = CollectionKind::where('title', 'Images')->firstOrFail();
        $this->assertSame('/images/collection/hero.jpg', $kind->hero_image);
        $this->assertSame('/images/collection/hero.jpg', $kind->image);
        $this->assertSame('/images/collection/badge.png', $kind->explorer_image);
    }

    public function test_collection_hero_image_uses_hero_column_before_legacy_image(): void
    {
        CollectionKind::create([
            'id' => 'image-priority',
            'title' => 'Image Priority',
            'image' => '/images/collection/old.jpg',
            'hero_image' => '/images/collection/hero.jpg',
            'explorer_image' => '/images/collection/badge.png',
            'is_published' => true,
        ]);

        $this->getJson('/api/collections/image-priority')->assertOk()
            ->assertJsonPath('heroImageUrl', url('/images/collection/hero.jpg'))
            ->assertJsonPath('imageUrl', url('/images/collection/hero.jpg'))
            ->assertJsonPath('explorerImageUrl', url('/images/collection/badge.png'));
    }

    public function test_admin_can_manage_sights_using_catalog_country_and_city_ids(): void
    {
        Country::create(['code' => 'TH', 'name' => 'Thailand', 'normalized_name' => 'thailand', 'continent_code' => 'AS']);
        City::create(['geoname_id' => '1609350', 'name' => 'Bangkok', 'normalized_name' => 'bangkok', 'country_code' => 'TH']);
        $headers = $this->adminHeaders();

        $sightId = $this->withHeaders($headers)->postJson('/admin/api/sights', ['name' => 'Wat Arun', 'countryId' => 'TH', 'cityId' => '1609350', 'content' => 'Temple of Dawn', 'unlocked' => true])
            ->assertCreated()->assertJsonPath('country', 'Thailand')->assertJsonPath('city', 'Bangkok')->json('id');
        $this->assertIsInt($sightId);
        $this->getJson('/api/sights/'.$sightId)->assertOk()->assertJsonPath('description', 'Temple of Dawn');

        $this->withHeaders($headers)->putJson('/admin/api/sights/'.$sightId, ['name' => 'Wat Arun', 'countryId' => 'TH', 'cityId' => '1609350', 'content' => 'Temple of Dawn'])
            ->assertOk()->assertJsonMissingPath('isPremium');
    }

    public function test_country_returns_all_ordered_sights_without_premium_fields(): void
    {
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        City::create(['geoname_id' => '2988507', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);

        foreach (['Arc de Triomphe', 'Eiffel Tower', 'Louvre Museum', 'Notre Dame'] as $name) {
            $this->withHeaders($this->adminHeaders())->postJson('/admin/api/sights', [
                'name' => $name,
                'countryId' => 'FR',
                'cityId' => '2988507',
                'content' => $name,
            ])->assertCreated();
        }

        $this->getJson('/api/countries/FR')
            ->assertOk()
            ->assertJsonCount(4, 'sights')
            ->assertJsonPath('sights.0.name', 'Arc de Triomphe')
            ->assertJsonPath('sights.3.name', 'Notre Dame')
            ->assertJsonMissingPath('sights.0.isPremium')
            ->assertJsonMissingPath('stats.premiumSights');
    }

    public function test_invalid_top_sight_save_returns_json_instead_of_an_html_error_page(): void
    {
        $this->withHeaders($this->adminHeaders())->postJson('/admin/api/sights', [])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors(['name', 'countryId', 'cityId']);
    }

    public function test_collection_and_daily_destination_locations_are_derived_from_catalog_city_ids(): void
    {
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        City::create(['geoname_id' => '2988507', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);
        $headers = $this->adminHeaders();

        $kind = $this->withHeaders($headers)->postJson('/admin/api/collections', ['id' => 'paris-icons', 'title' => 'Paris Icons', 'detail' => 'Essential Paris locations'])
            ->assertCreated()->assertJsonPath('title', 'Paris Icons')->assertJsonMissingPath('access');
        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', ['collectionKindId' => $kind->json('id'), 'title' => 'The Louvre', 'cityId' => '2988507', 'detail' => 'Museum', 'access' => 'pro'])
            ->assertCreated()->assertJsonPath('location', 'Paris, France')->assertJsonPath('collectionKindId', 'paris-icons')->assertJsonPath('access', 'pro');
        $this->getJson('/api/collections/paris-icons')->assertOk()
            ->assertJsonMissingPath('access')->assertJsonPath('places.0.city', 'Paris')
            ->assertJsonPath('places.0.countryId', 'FR')->assertJsonPath('places.0.access', 'pro')
            ->assertJsonPath('places.0.isPremium', true);
        $questions = collect(range(1, 5))->map(fn ($number) => ['information' => "Fact {$number}", 'prompt' => "Question {$number}?", 'imageUrl' => "/questions/{$number}.jpg", 'answers' => ['Paris', 'Rome'], 'correctAnswer' => 0, 'explanation' => 'Paris is in France.'])->all();
        $this->withHeaders($headers)->postJson('/admin/api/daily-destinations', ['countryId' => 'FR', 'imageUrl' => '/lessons/france.jpg', 'content' => 'France lesson', 'questions' => $questions])
            ->assertCreated()->assertJsonPath('country', 'France')->assertJsonPath('lessonNumber', 1)->assertJsonCount(5, 'questions');
    }

    public function test_collection_item_can_belong_to_more_than_one_collection_without_a_city(): void
    {
        $headers = $this->adminHeaders();
        $first = $this->withHeaders($headers)->postJson('/admin/api/collections', ['id' => 'first', 'title' => 'First'])->assertCreated();
        $second = $this->withHeaders($headers)->postJson('/admin/api/collections', ['id' => 'second', 'title' => 'Second'])->assertCreated();

        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', [
            'collectionKindIds' => [$first->json('id'), $second->json('id')],
            'title' => 'Shared item',
            'location' => 'Everywhere',
        ])->assertCreated()
            ->assertJsonPath('collectionKindIds', ['first', 'second'])
            ->assertJsonPath('location', 'Everywhere')
            ->assertJsonPath('access', 'free');

        $this->getJson('/api/v1/collections/first')->assertOk()->assertJsonPath('places.0.name', 'Shared item');
        $this->getJson('/api/v1/collections/second')->assertOk()->assertJsonPath('places.0.name', 'Shared item');
        $this->assertDatabaseCount('collection_kind_lists', 2);
    }

    public function test_admin_city_metadata_is_deduplicated_and_images_are_uploaded_to_public_storage(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        City::create(['geoname_id' => '1', 'name' => 'Springfield', 'normalized_name' => 'springfield', 'country_code' => 'US', 'subcountry' => 'Illinois']);
        City::create(['geoname_id' => '2', 'name' => 'Springfield', 'normalized_name' => 'springfield', 'country_code' => 'US', 'subcountry' => 'Missouri']);

        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/meta')->assertOk()->assertJsonMissingPath('cities');
        $this->withHeaders($this->adminHeaders())->postJson('/admin/api/states', ['countryId' => 'US', 'name' => 'California'])
            ->assertCreated()->assertJsonPath('name', 'California');
        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/states?country=US')->assertOk()->assertJsonFragment(['California']);
        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/cities?query=missouri&per_page=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('data.0.state', 'Missouri');
        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/cities?country=US')->assertOk()->assertJsonCount(1);
        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/states?country=US')->assertOk()->assertJsonCount(2);
        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/cities?country=US&state=Illinois')->assertOk()->assertJsonCount(1)->assertJsonPath('0.subcountry', 'Illinois');
        $this->getJson('/api/v1/catalog/countries/US/states/Illinois')->assertOk()->assertJsonPath('name', 'Illinois');
        $this->withHeaders($this->adminHeaders())->deleteJson('/admin/api/cities/2')->assertNoContent();
        $this->assertDatabaseMissing('cities', ['geoname_id' => '2']);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $response = $this->withHeaders($this->adminHeaders())->post('/admin/api/images', ['image' => UploadedFile::fake()->createWithContent('place.png', $png), 'folder' => 'sights']);
        $response->assertCreated();
        $path = parse_url($response->json('imageUrl'), PHP_URL_PATH);
        $this->assertStringStartsWith('/images/sights/', $path);
        $file = public_path(ltrim($path, '/'));
        $this->assertFileExists($file);
        unlink($file);
    }

    public function test_replacing_admin_and_user_images_removes_previous_server_files(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $headers = $this->adminHeaders();
        $old = $this->withHeaders($headers)->post('/admin/api/images', ['image' => UploadedFile::fake()->createWithContent('old.png', $png), 'folder' => 'collection'])->assertCreated()->json('imageUrl');
        $kind = $this->withHeaders($headers)->postJson('/admin/api/collections', ['title' => 'Images', 'imageUrl' => $old])->assertCreated();
        $new = $this->withHeaders($headers)->post('/admin/api/images', ['image' => UploadedFile::fake()->createWithContent('new.png', $png), 'folder' => 'collection'])->assertCreated()->json('imageUrl');
        $this->withHeaders($headers)->putJson('/admin/api/collections/'.$kind->json('id'), ['title' => 'Images', 'imageUrl' => $new])->assertOk();
        $this->assertFileDoesNotExist(public_path(ltrim($old, '/')));
        $this->assertFileExists(public_path(ltrim($new, '/')));

        Sanctum::actingAs(User::factory()->create());
        $first = $this->post('/api/v1/profile/image', ['image' => UploadedFile::fake()->createWithContent('user-old.png', $png)])->assertCreated()->json('photoUri');
        $second = $this->post('/api/v1/profile/image', ['image' => UploadedFile::fake()->createWithContent('user-new.png', $png)])->assertCreated()->json('photoUri');
        $this->assertStringStartsWith('/images/users/', $second);
        $this->assertFileDoesNotExist(public_path(ltrim($first, '/')));
        $this->assertFileExists(public_path(ltrim($second, '/')));

        unlink(public_path(ltrim($new, '/')));
        unlink(public_path(ltrim($second, '/')));
    }

    public function test_friend_codes_add_friends_and_filter_the_leaderboard(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create(['friend_code' => 'friend-token']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/friends/scan', ['code' => 'stampo://friend/friend-token'])->assertOk()->assertJsonPath('id', $friend->id);
        $this->getJson('/api/v1/me/community/leaderboard?scope=friends')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.level', 'Wanderer')
            ->assertJsonStructure(['0' => ['score', 'level', 'stats' => ['countries', 'continents', 'cities', 'collections']]]);
        $this->assertDatabaseCount('friends', 1);
    }

    public function test_global_leaderboard_is_public_but_friends_are_private(): void
    {
        User::factory()->count(2)->create();

        $this->getJson('/api/v1/community/leaderboard')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonMissingPath('0.email');
        $this->getJson('/api/v1/community/leaderboard?scope=friends')
            ->assertUnauthorized();
        $this->getJson('/api/v1/me/community/leaderboard?scope=friends')
            ->assertUnauthorized();
    }
}
