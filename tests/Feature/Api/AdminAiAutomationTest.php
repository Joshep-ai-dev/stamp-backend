<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateAiContent;
use App\Models\City;
use App\Models\Country;
use App\Models\Sight;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $this->getJson('/admin/api/ai/1/results')->assertUnauthorized();
        $this->postJson('/admin/api/ai', ['category' => 'countries'])->assertUnauthorized();
    }

    public function test_project_city_csv_uses_largest_match_and_creates_missing_catalog_cities(): void
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

        $path = tempnam(sys_get_temp_dir(), 'oxford-test-');
        file_put_contents($path, $csv);
        config()->set('ai.city_csv', $path);
        try {
            $response = $this->withHeader('X-Admin-Key', 'test-admin-key')
                ->postJson('/admin/api/ai', ['category' => 'cities', 'limit' => 3]);
        } finally {
            unlink($path);
        }

        $response->assertOk()->assertJsonPath('batch.total', 3);
        $batchId = $response->json('batch.id');
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $batchId, 'target_id' => (string) $paris->id]);
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $batchId, 'target_id' => (string) $newYork->id]);
        $this->assertDatabaseHas('cities', ['geoname_id' => 'oxford-2026-3', 'name' => 'Missing City']);
        Queue::assertPushed(GenerateAiContent::class, 3);
    }

    public function test_missing_project_csv_returns_actionable_error(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('queue.default', 'database');
        config()->set('ai.city_csv', '/missing/oxford.csv');
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson('/admin/api/ai', ['category' => 'cities'])
            ->assertStatus(503)->assertJsonPath('message', 'The project Oxford city CSV is missing or unreadable.');
        $this->assertDatabaseCount('ai_content_batches', 0);
    }

    public function test_results_are_paginated_scoped_and_include_saved_content_and_errors(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU',
            'hero_image' => '/images/countries/fr.webp', 'description' => 'Full description.']);
        $batch = DB::table('ai_content_batches')->insertGetId(['category' => 'countries', 'total' => 26]);
        DB::table('ai_content_items')->insert(['batch_id' => $batch, 'target_id' => 'FR', 'status' => 'failed', 'error' => 'Image failed']);
        for ($i = 1; $i <= 25; $i++) {
            DB::table('ai_content_items')->insert(['batch_id' => $batch, 'target_id' => 'missing-'.$i]);
        }
        $other = DB::table('ai_content_batches')->insertGetId(['category' => 'countries', 'total' => 1]);
        DB::table('ai_content_items')->insert(['batch_id' => $other, 'target_id' => 'OTHER']);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->getJson('/admin/api/ai/'.$batch.'/results')
            ->assertOk()->assertJsonCount(25, 'results.data')->assertJsonPath('results.total', 26)
            ->assertJsonPath('results.data.0.content.0.image', '/images/countries/fr.webp')
            ->assertJsonPath('results.data.0.content.0.description', 'Full description.')
            ->assertJsonPath('results.data.0.error', 'Image failed');
        $this->getJson('/admin/api/ai/'.$batch.'/results?per_page=5')->assertOk()->assertJsonCount(5, 'results.data')->assertJsonPath('results.last_page', 6);
        $this->getJson('/admin/api/ai/'.$batch.'/results?page=2')->assertOk()->assertJsonCount(1, 'results.data')
            ->assertJsonPath('results.data.0.name', 'Deleted catalog record');
        $this->getJson('/admin/api/ai/99999/results')->assertNotFound();
    }

    public function test_discovery_results_include_city_sights(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        $city = City::create(['geoname_id' => 'paris', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);
        Sight::create(['city_id' => $city->id, 'country_code' => 'FR', 'name' => 'Eiffel Tower', 'slug' => 'eiffel-tower',
            'description' => 'Tower description.', 'image_url' => '/images/sights/tower.webp']);
        $batch = DB::table('ai_content_batches')->insertGetId(['category' => 'discover-sights', 'total' => 1]);
        DB::table('ai_content_items')->insert(['batch_id' => $batch, 'target_id' => (string) $city->id, 'status' => 'complete']);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->getJson('/admin/api/ai/'.$batch.'/results')
            ->assertOk()->assertJsonPath('results.data.0.location', 'France')
            ->assertJsonPath('results.data.0.content.0.name', 'Eiffel Tower')
            ->assertJsonPath('results.data.0.content.0.image', '/images/sights/tower.webp');
    }

    private function sightBatch(string $status = 'complete'): array
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        $city = City::create(['geoname_id' => 'paris', 'name' => 'Paris', 'normalized_name' => 'paris', 'country_code' => 'FR']);
        $sight = Sight::create(['city_id' => $city->id, 'country_code' => 'FR', 'name' => 'Tower', 'slug' => 'tower', 'description' => 'Old description']);
        $batch = DB::table('ai_content_batches')->insertGetId(['category' => 'sights', 'status' => $status, 'total' => 1, 'completed' => $status === 'complete' ? 1 : 0]);
        DB::table('ai_content_items')->insert(['batch_id' => $batch, 'target_id' => (string) $sight->id, 'status' => $status === 'complete' ? 'complete' : 'queued']);

        return [$sight, $batch, $city];
    }

    public function test_admin_can_edit_and_approve_sight_content_from_results(): void
    {
        [$sight, $batch] = $this->sightBatch();
        $this->withHeader('X-Admin-Key', 'test-admin-key')->putJson("/admin/api/ai/{$batch}/content/{$sight->id}", [
            'name' => 'Eiffel Tower', 'description' => 'Updated full description.', 'image' => '/images/sights/new.webp', 'isFeatured' => true,
        ])->assertOk();
        $this->assertDatabaseHas('sights', ['id' => $sight->id, 'name' => 'Eiffel Tower', 'description' => 'Updated full description.', 'is_featured' => true]);
        $this->getJson("/admin/api/ai/{$batch}/results")->assertJsonPath('results.data.0.content.0.image', '/images/sights/new.webp');
    }

    public function test_content_mutations_require_authentication_and_batch_membership(): void
    {
        [$sight, $batch] = $this->sightBatch();
        $payload = ['name' => 'Other', 'description' => '', 'image' => ''];
        $this->putJson("/admin/api/ai/{$batch}/content/{$sight->id}", $payload)->assertUnauthorized();
        $this->deleteJson("/admin/api/ai/{$batch}/content/{$sight->id}")->assertUnauthorized();
        $other = Sight::create(['city_id' => $sight->city_id, 'country_code' => 'FR', 'name' => 'Other', 'slug' => 'other']);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->putJson("/admin/api/ai/{$batch}/content/{$other->id}", $payload)->assertNotFound();
        $this->deleteJson("/admin/api/ai/{$batch}/content/{$other->id}")->assertNotFound();
        $this->assertDatabaseHas('sights', ['id' => $other->id]);
    }

    public function test_removing_paused_sight_cancels_queued_item_and_updates_batch_counts(): void
    {
        [$sight, $batch] = $this->sightBatch('paused');
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson("/admin/api/ai/{$batch}/content/{$sight->id}")->assertNoContent();
        $this->assertDatabaseMissing('sights', ['id' => $sight->id]);
        $this->assertDatabaseMissing('ai_content_items', ['batch_id' => $batch]);
        $this->assertDatabaseHas('ai_content_batches', ['id' => $batch, 'total' => 0, 'completed' => 0, 'status' => 'complete']);
    }

    public function test_mutations_cannot_overwrite_pending_generation(): void
    {
        [$sight, $batch] = $this->sightBatch('running');
        $this->withHeader('X-Admin-Key', 'test-admin-key')->putJson("/admin/api/ai/{$batch}/content/{$sight->id}", [
            'name' => 'Changed', 'description' => '', 'image' => '',
        ])->assertStatus(409);
        $this->deleteJson("/admin/api/ai/{$batch}/content/{$sight->id}")->assertStatus(409);
        DB::table('ai_content_batches')->where('id', $batch)->update(['status' => 'paused']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['status' => 'working']);
        $this->deleteJson("/admin/api/ai/{$batch}/content/{$sight->id}")->assertStatus(409);
        $this->assertDatabaseHas('sights', ['id' => $sight->id, 'name' => 'Tower']);
    }

    public function test_discovered_sights_can_be_removed_without_deleting_the_city(): void
    {
        [$sight, $batch, $city] = $this->sightBatch();
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => 'discover-sights']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => (string) $city->id]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson("/admin/api/ai/{$batch}/content/{$sight->id}")->assertNoContent();
        $this->assertDatabaseMissing('sights', ['id' => $sight->id]);
        $this->assertDatabaseHas('cities', ['id' => $city->id]);
        $this->getJson("/admin/api/ai/{$batch}/results")->assertJsonCount(0, 'results.data.0.content');
    }

    public function test_destination_content_can_be_cleared_without_deleting_catalog_record(): void
    {
        [$sight, $batch] = $this->sightBatch();
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => 'countries']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => 'FR']);
        Country::where('code', 'FR')->update(['hero_image' => '/images/countries/fr.webp', 'description' => 'France description']);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson("/admin/api/ai/{$batch}/content/FR")->assertNoContent();
        $this->assertDatabaseHas('countries', ['code' => 'FR', 'hero_image' => '', 'description' => '']);
    }
}
