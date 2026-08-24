<?php

namespace Tests\Feature\Api;

use App\Models\City;
use App\Models\Country;
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

    public function test_admin_can_manage_sights_using_catalog_country_and_city_ids(): void
    {
        Country::create(['code' => 'TH', 'name' => 'Thailand', 'normalized_name' => 'thailand', 'continent_code' => 'AS']);
        City::create(['geoname_id' => '1609350', 'name' => 'Bangkok', 'normalized_name' => 'bangkok', 'country_code' => 'TH']);
        $headers = $this->adminHeaders();

        $this->withHeaders($headers)->postJson('/admin/api/sights', ['id' => 'wat-arun', 'name' => 'Wat Arun', 'countryId' => 'TH', 'cityId' => '1609350', 'content' => 'Temple of Dawn', 'unlocked' => true])
            ->assertCreated()->assertJsonPath('country', 'Thailand')->assertJsonPath('city', 'Bangkok');
        $this->getJson('/api/sights/wat-arun')->assertOk()->assertJsonPath('description', 'Temple of Dawn');
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
            ->assertCreated()->assertJsonPath('title', 'Paris Icons');
        $this->withHeaders($headers)->postJson('/admin/api/collection-lists', ['collectionKindId' => $kind->json('id'), 'title' => 'The Louvre', 'cityId' => '2988507', 'detail' => 'Museum', 'access' => 'free'])
            ->assertCreated()->assertJsonPath('location', 'Paris, France')->assertJsonPath('collectionKindId', 'paris-icons');
        $this->getJson('/api/collections/paris-icons')->assertOk()->assertJsonPath('places.0.city', 'Paris')->assertJsonPath('places.0.countryId', 'FR');
        $this->withHeaders($headers)->postJson('/admin/api/daily-destinations', ['name' => 'The Louvre', 'countryId' => 'FR', 'cityId' => '2988507', 'content' => 'Museum lesson', 'question' => 'Where is it?', 'options' => ['Paris', 'Rome'], 'correctAnswer' => 0])
            ->assertCreated()->assertJsonPath('country', 'France')->assertJsonPath('city', 'Paris')->assertJsonPath('cityId', '2988507');
    }

    public function test_admin_city_metadata_is_deduplicated_and_images_are_uploaded_to_public_storage(): void
    {
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        City::create(['geoname_id' => '1', 'name' => 'Springfield', 'normalized_name' => 'springfield', 'country_code' => 'US', 'subcountry' => 'Illinois']);
        City::create(['geoname_id' => '2', 'name' => 'Springfield', 'normalized_name' => 'springfield', 'country_code' => 'US', 'subcountry' => 'Missouri']);

        $this->withHeaders($this->adminHeaders())->getJson('/admin/api/meta')->assertOk()->assertJsonCount(1, 'cities');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $response = $this->withHeaders($this->adminHeaders())->post('/admin/api/images', ['image' => UploadedFile::fake()->createWithContent('place.png', $png)]);
        $response->assertCreated();
        $path = parse_url($response->json('imageUrl'), PHP_URL_PATH);
        $this->assertStringStartsWith('/images/', $path);
        $file = public_path('images/'.basename($path));
        $this->assertFileExists($file);
        unlink($file);
    }

    public function test_friend_codes_add_friends_and_filter_the_leaderboard(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create(['friend_code' => 'friend-token']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/friends/scan', ['code' => 'stampo://friend/friend-token'])->assertOk()->assertJsonPath('id', $friend->id);
        $this->getJson('/api/v1/community/leaderboard?scope=friends')->assertOk()->assertJsonCount(2);
        $this->assertDatabaseCount('friends', 1);
    }
}
