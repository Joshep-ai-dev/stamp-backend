<?php

namespace Tests\Feature\Api;

use App\Jobs\GenerateAiContent;
use App\Models\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
