<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateAiContent;
use App\Models\City;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminAiAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_only_countries_with_missing_content(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('queue.default', 'database');
        Queue::fake();

        Country::create(['code' => 'AA', 'name' => 'Example A', 'normalized_name' => 'example a', 'continent_code' => 'EU']);
        Country::create(['code' => 'BB', 'name' => 'Example B', 'normalized_name' => 'example b', 'continent_code' => 'EU',
            'hero_image' => '/images/countries/existing.webp', 'description' => 'Existing description.']);

        $response = $this->withHeader('X-Admin-Key', 'test-admin-key')
            ->postJson('/admin/api/ai', ['category' => 'countries', 'limit' => 25]);

        $response->assertOk()->assertJsonPath('batch.total', 1);
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $response->json('batch.id'), 'target_id' => 'AA']);
        $this->assertDatabaseMissing('ai_content_items', ['batch_id' => $response->json('batch.id'), 'target_id' => 'BB']);
        Queue::assertPushed(GenerateAiContent::class, 1);
    }

    public function test_ai_endpoints_require_admin_key(): void
    {
        $this->getJson('/admin/api/ai')->assertUnauthorized();
        $this->postJson('/admin/api/ai', ['category' => 'countries'])->assertUnauthorized();
    }

    public function test_ranked_city_upload_uses_largest_match_and_creates_missing_catalog_cities(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        config()->set('services.openai.api_key', 'test-openai-key');
        config()->set('queue.default', 'database');
        Queue::fake();

        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        City::create(['geoname_id' => 'paris-small', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR', 'population' => 1000]);
        $paris = City::create(['geoname_id' => 'paris-large', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR', 'population' => 2000000]);
        $newYork = City::create(['geoname_id' => 'new-york', 'name' => 'New York', 'normalized_name' => 'new york', 'country_code' => 'US', 'population' => 8000000]);
        $csv = "rank,city,country\n1,Paris,France\n2,New York City,United States\n3,Missing City,France\n";
        for ($rank = 4; $rank <= 1000; $rank++) {
            $csv .= "{$rank},Ranked Place {$rank},France\n";
        }

        $response = $this->withHeaders(['X-Admin-Key' => 'test-admin-key', 'Accept' => 'application/json'])
            ->post('/admin/api/ai', [
                'category' => 'cities', 'limit' => 3,
                'cityCsv' => UploadedFile::fake()->createWithContent('oxford-cities.csv', $csv),
            ]);

        $response->assertOk()->assertJsonPath('batch.total', 3);
        $batchId = $response->json('batch.id');
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $batchId, 'target_id' => (string) $paris->id]);
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $batchId, 'target_id' => (string) $newYork->id]);
        $this->assertDatabaseHas('cities', ['geoname_id' => 'oxford-2026-3', 'name' => 'Missing City']);
        Queue::assertPushed(GenerateAiContent::class, 3);
    }
}
