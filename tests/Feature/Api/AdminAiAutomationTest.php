<?php

namespace Tests\Feature\Api;

use App\Exceptions\AiRateLimitedException;
use App\Jobs\GenerateAiContent;
use App\Models\City;
use App\Models\Country;
use App\Models\Sight;
use App\Services\AiRateLimit;
use App\Services\AiStampGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAiAutomationTest extends TestCase
{
    use RefreshDatabase;

    private string $testPublicPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPublicPath = sys_get_temp_dir().'/kroo-ai-test-'.bin2hex(random_bytes(8));
        $this->app->usePublicPath($this->testPublicPath);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testPublicPath);
        parent::tearDown();
    }

    private function fakeImage(): array
    {
        return ['data' => [['b64_json' => 'UklGRg4CAABXRUJQVlA4WAoAAAAgAAAACwAABwAASUNDUMgBAAAAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADZWUDggIAAAAFABAJ0BKgwACAACgEIlAE6AKAAA/vPevodcXNkWkAAA']]];
    }

    public function test_text_requests_reduce_reasoning_and_limit_tokens_without_changing_description_prompt(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.text_model', 'gpt-5-mini');
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Description.']]]]])]);
        app(AiStampGenerator::class)->description('Country', 'France');
        app(AiStampGenerator::class)->generateMany(['x' => ['category' => 'Country', 'name' => 'France', 'description' => true]]);
        $expected = 'Write a factual 90–150 word travel description for the Country France. Explain its location, significance, and visitor highlights in two short paragraphs. Return only the description.';
        foreach (Http::recorded() as [$request, $response]) {
            $this->assertSame($expected, $request['input']);
            $this->assertSame('minimal', $request['reasoning']['effort']);
            $this->assertSame(1024, $request['max_output_tokens']);
        }
        Http::assertSentCount(2);
    }

    public function test_non_reasoning_model_gets_token_limit_without_unsupported_reasoning_option(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('services.openai.text_model', 'gpt-4.1-mini');
        config()->set('ai.text_max_output_tokens', 2048);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Description.']]]]])]);
        app(AiStampGenerator::class)->description('Country', 'France');
        Http::assertSent(fn ($request) => $request['max_output_tokens'] === 2048 && ! isset($request['reasoning']));
    }

    public function test_token_truncated_description_is_not_saved(): void
    {
        [$sight, $batch] = $this->sightBatch('running');
        $sight->update(['description' => '']);
        config()->set('services.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens'],
            'output' => [['content' => [['type' => 'output_text', 'text' => 'Truncated description']]]],
        ])]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.failed', 1);
        $this->assertSame('', $sight->fresh()->description);
        Http::assertSentCount(1);
    }

    public static function rateLimitedSightCategories(): array
    {
        return ['existing sight' => ['sights'], 'city top sights' => ['discover-sights']];
    }

    #[DataProvider('rateLimitedSightCategories')]
    public function test_image_rate_limit_waits_then_resumes_without_losing_content(string $category): void
    {
        [$sight, $batch, $city] = $this->sightBatch('running');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('ai.concurrency', 8);
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => $category]);
        if ($category === 'discover-sights') {
            DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => (string) $city->id]);
        }
        Http::fake(['api.openai.com/v1/images/generations' => Http::sequence()
            ->push(['error' => ['message' => 'Rate limit reached for image generation.', 'code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '60'])
            ->push($this->fakeImage())]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/process")->assertOk()
            ->assertJsonPath('batch.status', 'running')->assertJsonPath('batch.failed', 0)->assertJsonPath('retryAfterSeconds', 60);
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $batch, 'status' => 'queued']);
        $this->assertSame('Old description', $sight->fresh()->description);
        $this->getJson('/admin/api/ai')->assertJsonPath('concurrency', 4)->assertJsonPath('retryAfterSeconds', 60);
        $this->postJson("/admin/api/ai/{$batch}/process")->assertJsonPath('processed', false);
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.status', 'complete')
            ->assertJsonPath('batch.completed', 1)->assertJsonPath('batch.failed', 0)->assertJsonPath('retryAfterSeconds', 0);
        $this->assertSame('Old description', $sight->fresh()->description);
        $this->get($sight->fresh()->image_url)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_parallel_rate_limits_share_one_cooldown_and_reduce_parallelism_once(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('ai.concurrency', 8);
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['error' => ['message' => 'Rate limit reached.']], 429, ['Retry-After' => '45'])]);
        $tasks = [];
        foreach (range(1, 3) as $id) {
            $tasks[$id] = ['category' => 'Country', 'name' => 'Example '.$id, 'image' => true, 'folder' => 'countries'];
        }
        $results = app(AiStampGenerator::class)->generateMany($tasks);
        foreach ($results as $result) {
            $this->assertInstanceOf(AiRateLimitedException::class, $result['error']);
        }
        $this->assertSame(4, AiStampGenerator::concurrency());
        $this->assertSame(45, AiRateLimit::retryAfter());
        app(AiStampGenerator::class)->generateMany($tasks);
        Http::assertSentCount(3);
    }

    public function test_quota_429_is_not_treated_as_a_temporary_rate_limit(): void
    {
        [$sight, $batch] = $this->sightBatch('running');
        config()->set('services.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(['error' => ['message' => 'You exceeded your current quota.', 'code' => 'insufficient_quota']], 429)]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/process")->assertOk()
            ->assertJsonPath('batch.failed', 1)->assertJsonPath('retryAfterSeconds', 0);
        $this->postJson('/admin/api/ai/recover-rate-limits')->assertOk()->assertJsonPath('recovered', 0);
        Http::assertSentCount(1);
    }

    public function test_old_rate_limit_failures_are_recovered_automatically_without_resuming_paused_batches(): void
    {
        [$sight, $batch] = $this->sightBatch();
        DB::table('ai_content_batches')->where('id', $batch)->update(['completed' => 0, 'failed' => 1]);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['status' => 'failed', 'error' => 'HTTP request returned status code 429: Rate limit reached for gpt-image (truncated...)']);
        $paused = DB::table('ai_content_batches')->insertGetId(['category' => 'sights', 'status' => 'paused', 'total' => 1, 'failed' => 1]);
        DB::table('ai_content_items')->insert(['batch_id' => $paused, 'target_id' => (string) $sight->id, 'status' => 'failed', 'error' => 'Rate limit reached.']);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson('/admin/api/ai/recover-rate-limits')->assertOk()->assertJsonPath('recovered', 1);
        $this->assertDatabaseHas('ai_content_batches', ['id' => $batch, 'status' => 'running', 'failed' => 0]);
        $this->assertDatabaseHas('ai_content_items', ['batch_id' => $batch, 'status' => 'queued', 'error' => null]);
        $this->assertDatabaseHas('ai_content_batches', ['id' => $paused, 'status' => 'paused', 'failed' => 1]);
        $this->postJson('/admin/api/ai/recover-rate-limits')->assertJsonPath('recovered', 0);
        Http::assertNothingSent();
    }

    public function test_retry_delay_uses_reset_headers_and_increases_when_no_hint_is_available(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        config()->set('ai.concurrency', 8);
        Http::fake(['api.openai.com/v1/images/generations' => Http::sequence()
            ->push(['error' => ['message' => 'Rate limit reached.']], 429, ['x-ratelimit-reset-requests' => '1m5s'])
            ->push(['error' => ['message' => 'Rate limit reached.']], 429)]);
        $task = ['x' => ['category' => 'Country', 'name' => 'France', 'image' => true, 'folder' => 'countries']];
        app(AiStampGenerator::class)->generateMany($task);
        $this->assertSame(65, AiRateLimit::retryAfter());
        $this->travel(66)->seconds();
        app(AiStampGenerator::class)->generateMany($task);
        $this->assertSame(60, AiRateLimit::retryAfter());
        $this->assertSame(2, AiStampGenerator::concurrency());
        Http::assertSentCount(2);
    }

    public function test_empty_success_responses_are_retried_for_standalone_generation(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        Sleep::fake();
        Http::fake(['api.openai.com/v1/responses' => Http::sequence()
            ->push('', 200)->push('{"output":', 200)
            ->push(['output' => [['content' => [['type' => 'output_text', 'text' => 'Recovered description.']]]]])]);

        $this->assertSame('Recovered description.', app(AiStampGenerator::class)->description('Country', 'France'));
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request['stream'] === false);
    }

    public function test_parallel_generation_retries_only_the_unreadable_response(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        Sleep::fake();
        $calls = ['France' => 0, 'Italy' => 0];
        Http::fake(['api.openai.com/v1/responses' => function ($request) use (&$calls) {
            $name = str_contains($request['input'], 'France') ? 'France' : 'Italy';
            $calls[$name]++;

            return $name === 'France' && $calls[$name] === 1 ? Http::response('', 200)
                : Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => $name.' description.']]]]]);
        }]);

        $result = app(AiStampGenerator::class)->generateMany([
            'fr' => ['category' => 'Country', 'name' => 'France', 'description' => true],
            'it' => ['category' => 'Country', 'name' => 'Italy', 'description' => true],
        ]);
        $this->assertSame('France description.', $result['fr']['description']);
        $this->assertSame('Italy description.', $result['it']['description']);
        $this->assertSame(['France' => 2, 'Italy' => 1], $calls);
        Http::assertSentCount(3);
    }

    public function test_response_with_utf8_bom_is_decoded_without_retry(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        Http::fake(['api.openai.com/v1/responses' => Http::response("\xEF\xBB\xBF".json_encode([
            'output' => [['content' => [['type' => 'output_text', 'text' => 'Valid description.']]]]], JSON_THROW_ON_ERROR))]);

        $this->assertSame('Valid description.', app(AiStampGenerator::class)->description('Country', 'France'));
        Http::assertSentCount(1);
    }

    public function test_completed_text_event_stream_is_read_as_a_response(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        $event = ['type' => 'response.completed', 'response' => ['output' => [['content' => [['type' => 'output_text', 'text' => 'Streamed description.']]]]]];
        Http::fake(['api.openai.com/v1/responses' => Http::response(
            "event: response.created\r\ndata: {\"type\":\"response.created\"}\r\n\r\nevent: response.completed\r\ndata: ".json_encode($event)."\r\n\r\ndata: [DONE]\r\n\r\n",
            200, ['Content-Type' => 'text/event-stream'],
        )]);

        $this->assertSame('Streamed description.', app(AiStampGenerator::class)->description('Country', 'France'));
        Http::assertSentCount(1);
    }

    public function test_completed_image_event_stream_is_saved_as_an_image(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        $event = ['type' => 'image_generation.completed', 'b64_json' => $this->fakeImage()['data'][0]['b64_json']];
        Http::fake(['api.openai.com/v1/images/generations' => Http::response(
            "event: image_generation.completed\ndata: ".json_encode($event)."\n\n", 200, ['Content-Type' => 'text/event-stream'],
        )]);

        $image = app(AiStampGenerator::class)->image('Country', 'France', 'countries');
        $this->get($image)->assertOk();
        Http::assertSentCount(1);
    }

    public function test_unreadable_image_exhausts_retries_and_keeps_the_description_and_other_results(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        Sleep::fake();
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Saved description.']]]]]),
            'api.openai.com/v1/images/generations' => fn ($request) => str_contains($request['prompt'], 'France')
                ? Http::response('<html>Private gateway details</html>', 200, ['Content-Type' => 'text/html', 'x-request-id' => 'req-test'])
                : Http::response($this->fakeImage()),
        ]);

        $result = app(AiStampGenerator::class)->generateMany([
            'fr' => ['category' => 'Country', 'name' => 'France', 'description' => true, 'image' => true, 'folder' => 'countries'],
            'it' => ['category' => 'Country', 'name' => 'Italy', 'description' => true, 'image' => true, 'folder' => 'countries'],
        ]);
        $this->assertSame('Saved description.', $result['fr']['description']);
        $this->assertInstanceOf(\UnexpectedValueException::class, $result['fr']['error']);
        $this->assertStringContainsString('text/html', $result['fr']['error']->getMessage());
        $this->assertStringContainsString('req-test', $result['fr']['error']->getMessage());
        $this->assertStringNotContainsString('Private gateway details', $result['fr']['error']->getMessage());
        $this->assertArrayNotHasKey('image', $result['fr']);
        $this->get($result['it']['image'])->assertOk();
        Http::assertSentCount(6);
    }

    public function test_partial_image_event_stream_is_retried_instead_of_saved(): void
    {
        config()->set('services.openai.api_key', 'test-key');
        Sleep::fake();
        $event = ['type' => 'image_generation.partial_image', 'b64_json' => $this->fakeImage()['data'][0]['b64_json']];
        Http::fake(['api.openai.com/v1/images/generations' => Http::sequence()
            ->push('data: '.json_encode($event)."\n\n", 200, ['Content-Type' => 'text/event-stream'])
            ->push($this->fakeImage())]);

        $image = app(AiStampGenerator::class)->image('Country', 'France', 'countries');
        $this->get($image)->assertOk();
        Http::assertSentCount(2);
        $this->assertCount(1, File::files(public_path('images/countries')));
    }

    public function test_parallel_limit_is_reported_consistently_and_capped_at_twelve(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        foreach ([8 => 8, 99 => 12, 0 => 1] as $configured => $expected) {
            config()->set('ai.concurrency', $configured);
            $this->withHeader('X-Admin-Key', 'test-admin-key')->getJson('/admin/api/ai')->assertOk()->assertJsonPath('concurrency', $expected);
            $this->assertSame($expected, AiStampGenerator::concurrency());
        }
    }

    public function test_section_counts_include_all_history_and_count_each_sight_only_once(): void
    {
        [$sight, $batch, $city] = $this->sightBatch();
        $sight->update(['image_url' => '/images/sights/saved.webp']);
        $discovery = DB::table('ai_content_batches')->insertGetId(['category' => 'discover-sights', 'status' => 'running', 'total' => 3, 'completed' => 1, 'failed' => 1]);
        DB::table('ai_content_items')->insert(['batch_id' => $discovery, 'target_id' => (string) $city->id, 'status' => 'complete']);
        Country::where('code', 'FR')->update(['hero_image' => '/images/countries/fr.webp', 'description' => 'Country description.']);
        foreach (range(1, 25) as $index) {
            $id = DB::table('ai_content_batches')->insertGetId(['category' => 'countries', 'status' => 'complete', 'total' => 1, 'completed' => 1]);
            DB::table('ai_content_items')->insert(['batch_id' => $id, 'target_id' => 'FR', 'status' => 'complete']);
        }
        $this->withHeader('X-Admin-Key', 'test-admin-key')->getJson('/admin/api/ai')->assertOk()
            ->assertJsonPath('sectionCounts.countries.completed', 25)
            ->assertJsonPath('sectionCounts.countries.images', 1)
            ->assertJsonPath('sectionCounts.countries.descriptions', 1)
            ->assertJsonPath('sectionCounts.countries.records', 1)
            ->assertJsonPath('sectionCounts.discover-sights.completed', 2)
            ->assertJsonPath('sectionCounts.discover-sights.pending', 1)
            ->assertJsonPath('sectionCounts.discover-sights.failed', 1)
            ->assertJsonPath('sectionCounts.discover-sights.images', 1)
            ->assertJsonPath('sectionCounts.discover-sights.descriptions', 1)
            ->assertJsonPath('sectionCounts.discover-sights.records', 1)
            ->assertJsonPath('sectionCounts.states.images', 0)
            ->assertJsonPath('sectionCounts.cities.total', 0);
    }

    public function test_recent_batches_are_limited_per_section_with_sight_categories_merged(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        $countryId = DB::table('ai_content_batches')->insertGetId(['category' => 'countries', 'status' => 'complete', 'total' => 0]);
        DB::table('ai_content_batches')->insert(['category' => 'states', 'status' => 'complete', 'total' => 0]);
        foreach (range(1, 25) as $index) {
            DB::table('ai_content_batches')->insert(['category' => 'cities', 'status' => 'complete', 'total' => 0]);
            DB::table('ai_content_batches')->insert(['category' => $index % 2 ? 'sights' : 'discover-sights', 'status' => 'complete', 'total' => 0]);
        }

        $response = $this->withHeader('X-Admin-Key', 'test-admin-key')->getJson('/admin/api/ai')->assertOk();
        $batches = collect($response->json('batches'));
        $this->assertCount(42, $batches);
        $this->assertTrue($batches->contains('id', $countryId));
        $this->assertCount(20, $batches->where('category', 'cities'));
        $this->assertCount(20, $batches->whereIn('category', ['sights', 'discover-sights']));
        $this->assertCount(1, $batches->where('category', 'states'));
        $this->assertSame($batches->sortByDesc('id')->pluck('id')->values()->all(), $batches->pluck('id')->all());
    }

    public function test_admin_can_start_only_countries_with_missing_content_without_queue_worker(): void
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
        Queue::assertNothingPushed();
    }

    public function test_ai_endpoints_require_admin_key(): void
    {
        $this->getJson('/admin/api/ai')->assertUnauthorized();
        $this->postJson('/admin/api/ai/recover-rate-limits')->assertUnauthorized();
        $this->getJson('/admin/api/ai/1/results')->assertUnauthorized();
        $this->postJson('/admin/api/ai', ['category' => 'countries'])->assertUnauthorized();
        $this->postJson('/admin/api/ai/1/process')->assertUnauthorized();
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
        Queue::assertNothingPushed();
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

    public function test_city_batch_entry_can_be_removed_while_preserving_saved_catalog_content(): void
    {
        [$sight, $batch, $city] = $this->sightBatch();
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => 'discover-sights']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => (string) $city->id]);
        $item = DB::table('ai_content_items')->where('batch_id', $batch)->first();
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson("/admin/api/ai/{$batch}/items/{$item->id}")->assertNoContent();
        $this->assertDatabaseMissing('ai_content_items', ['id' => $item->id]);
        $this->assertDatabaseHas('ai_content_batches', ['id' => $batch, 'total' => 0, 'completed' => 0, 'failed' => 0]);
        $this->assertDatabaseHas('cities', ['id' => $city->id]);
        $this->assertDatabaseHas('sights', ['id' => $sight->id]);
        $this->getJson("/admin/api/ai/{$batch}/results")->assertJsonCount(0, 'results.data');
    }

    public function test_batch_entry_removal_requires_authentication_and_batch_membership(): void
    {
        [$sight, $batch] = $this->sightBatch();
        $item = DB::table('ai_content_items')->where('batch_id', $batch)->first();
        $this->deleteJson("/admin/api/ai/{$batch}/items/{$item->id}")->assertUnauthorized();
        $other = DB::table('ai_content_batches')->insertGetId(['category' => 'sights', 'status' => 'complete', 'total' => 0]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson("/admin/api/ai/{$other}/items/{$item->id}")->assertNotFound();
        $this->assertDatabaseHas('ai_content_items', ['id' => $item->id]);
    }

    public function test_removing_paused_entry_cancels_job_and_preserves_batch_counts(): void
    {
        [$sight, $batch] = $this->sightBatch('running');
        $item = DB::table('ai_content_items')->where('batch_id', $batch)->first();
        $url = "/admin/api/ai/{$batch}/items/{$item->id}";
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson($url)->assertStatus(409);
        DB::table('ai_content_batches')->where('id', $batch)->update(['status' => 'paused']);
        DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'working']);
        $this->deleteJson($url)->assertStatus(409);
        DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'queued']);
        $this->deleteJson($url)->assertNoContent();
        $generator = \Mockery::mock(AiStampGenerator::class);
        $generator->shouldNotReceive('description');
        $generator->shouldNotReceive('image');
        (new GenerateAiContent($item->id))->handle($generator);
        $this->assertDatabaseHas('ai_content_batches', ['id' => $batch, 'total' => 0, 'completed' => 0, 'status' => 'complete']);
        $this->assertDatabaseHas('sights', ['id' => $sight->id]);
    }

    public function test_removing_failed_entry_updates_failed_counter(): void
    {
        [$sight, $batch] = $this->sightBatch('complete');
        DB::table('ai_content_batches')->where('id', $batch)->update(['completed' => 0, 'failed' => 1]);
        $item = DB::table('ai_content_items')->where('batch_id', $batch)->first();
        DB::table('ai_content_items')->where('id', $item->id)->update(['status' => 'failed']);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->deleteJson("/admin/api/ai/{$batch}/items/{$item->id}")->assertNoContent();
        $this->assertDatabaseHas('ai_content_batches', ['id' => $batch, 'total' => 0, 'failed' => 0, 'completed' => 0]);
    }

    public function test_batch_processes_real_generation_code_without_queue_worker(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('queue.default', 'sync');
        config()->set('ai.concurrency', 1);
        Queue::fake();
        Http::fake(['api.openai.com/v1/images/generations' => Http::response($this->fakeImage())]);
        Country::create(['code' => 'FR', 'name' => 'France', 'normalized_name' => 'france', 'continent_code' => 'EU']);
        Country::create(['code' => 'US', 'name' => 'United States', 'normalized_name' => 'united states', 'continent_code' => 'NA']);
        $batch = $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson('/admin/api/ai', ['category' => 'countries'])->assertOk()->json('batch.id');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('processed', true)->assertJsonPath('batch.completed', 1)->assertJsonPath('batch.status', 'running');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', 2)->assertJsonPath('batch.status', 'complete');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('processed', false)->assertJsonPath('batch.completed', 2);
        $this->assertNotEmpty(Country::find('FR')->hero_image);
        $this->assertNull(Country::find('FR')->description);
        Http::assertSentCount(2);
        Queue::assertNothingPushed();
    }

    public function test_waiting_discovery_batch_processes_existing_items_directly(): void
    {
        [$sight, $batch, $city] = $this->sightBatch('running');
        config()->set('services.openai.api_key', 'test-key');
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => 'discover-sights']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => (string) $city->id]);
        $sight->delete();
        Http::fake([
            'api.openai.com/v1/responses' => fn ($request) => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => str_starts_with($request['input'], 'Identify')
                ? '["Louvre", "Eiffel Tower", "Notre Dame", "Arc de Triomphe", "Sacre Coeur"]' : 'Saved sight description.']]]]]),
            'api.openai.com/v1/images/generations' => Http::response($this->fakeImage()),
        ]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', 1)->assertJsonPath('batch.status', 'complete');
        $this->assertDatabaseHas('sights', ['city_id' => $city->id, 'name' => 'Louvre', 'is_featured' => false]);
        $louvre = Sight::where('name', 'Louvre')->first();
        $this->assertSame('Saved sight description.', $louvre->description);
        $this->assertNotEmpty($louvre->image_url);
        $this->get($louvre->image_url)->assertOk();
        Http::assertSentCount(11);
    }

    public function test_direct_processing_respects_pause_and_records_errors_for_retry(): void
    {
        [$sight, $batch] = $this->sightBatch('paused');
        config()->set('services.openai.api_key', 'test-key');
        $generator = $this->mock(AiStampGenerator::class);
        $itemId = DB::table('ai_content_items')->where('batch_id', $batch)->value('id');
        $generator->shouldReceive('generateMany')->once()->andReturn([$itemId => ['error' => new \RuntimeException('Image generation unavailable.')]]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('processed', false);
        $this->postJson("/admin/api/ai/{$batch}/resume")->assertOk();
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.failed', 1)->assertJsonPath('errors.0.error', 'Image generation unavailable.');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('processed', false)->assertJsonPath('batch.failed', 1);
    }

    public function test_direct_processing_does_not_duplicate_work_when_another_page_holds_the_lock(): void
    {
        [$sight, $batch] = $this->sightBatch('running');
        config()->set('services.openai.api_key', 'test-key');
        $generator = $this->mock(AiStampGenerator::class);
        $generator->shouldNotReceive('image');
        $lock = Cache::store('database')->lock('ai-content-batch:'.$batch, 1200);
        $this->assertTrue($lock->get());
        try {
            $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('processed', false)->assertJsonPath('batch.completed', 0);
        } finally {
            $lock->release();
        }
    }

    public static function parallelLimits(): array
    {
        return ['three at a time' => [3], 'eight at a time' => [8]];
    }

    #[DataProvider('parallelLimits')]
    public function test_parallel_group_saves_generated_images_and_descriptions_and_preserves_item_order(int $concurrency): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('ai.concurrency', $concurrency);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Generated description.']]]]]),
            'api.openai.com/v1/images/generations' => Http::response($this->fakeImage()),
        ]);
        $codes = array_map(fn ($index) => str_repeat(chr(65 + $index), 2), range(0, $concurrency));
        foreach ($codes as $code) {
            Country::create(['code' => $code, 'name' => 'Example '.$code, 'normalized_name' => 'example '.strtolower($code), 'continent_code' => 'EU']);
        }
        $batch = $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson('/admin/api/ai', ['category' => 'countries'])->assertOk()->json('batch.id');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', $concurrency)->assertJsonPath('batch.status', 'running');
        foreach (array_slice($codes, 0, $concurrency) as $code) {
            $country = Country::find($code);
            $this->assertNull($country->description);
            $this->assertNotEmpty($country->hero_image);
            $this->assertFileExists(public_path(ltrim($country->hero_image, '/')));
            $this->get($country->hero_image)->assertOk();
        }
        $this->assertEmpty(Country::find($codes[$concurrency])->hero_image);
        $this->getJson("/admin/api/ai/{$batch}/results")->assertJsonPath('results.data.0.targetId', 'AA')
            ->assertJsonPath('results.data.1.targetId', 'BB')->assertJsonPath('results.data.2.targetId', 'CC');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/images/generations')
            && $request['output_format'] === 'webp' && str_contains($request['prompt'], 'deep forest-green ink'));
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', $concurrency + 1)->assertJsonPath('batch.status', 'complete');
        Http::assertSentCount($concurrency + 1);
    }

    public function test_failed_image_does_not_discard_description_or_block_other_parallel_items(): void
    {
        config()->set('services.stampo.admin_key', 'test-admin-key');
        config()->set('services.openai.api_key', 'test-key');
        config()->set('ai.concurrency', 3);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response(['output' => [['content' => [['type' => 'output_text', 'text' => 'Saved description.']]]]]),
            'api.openai.com/v1/images/generations' => fn ($request) => Http::response(str_contains($request['prompt'], 'Example AA') ? ['data' => []] : $this->fakeImage()),
        ]);
        foreach (['AA', 'BB', 'CC'] as $code) {
            Country::create(['code' => $code, 'name' => 'Example '.$code, 'normalized_name' => 'example '.strtolower($code), 'continent_code' => 'EU']);
        }
        $batch = $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson('/admin/api/ai', ['category' => 'countries'])->json('batch.id');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', 2)->assertJsonPath('batch.failed', 1)
            ->assertJsonPath('errors.0.error', 'The image model returned no readable image.');
        $this->assertNull(Country::find('AA')->description);
        $this->assertNotEmpty(Country::find('BB')->hero_image);
        $this->assertNotEmpty(Country::find('CC')->hero_image);
    }

    public function test_completed_discovery_can_fill_existing_sight_image_without_regenerating_description_or_names(): void
    {
        [$sight, $batch, $city] = $this->sightBatch();
        $sight->update(['is_featured' => true]);
        config()->set('services.openai.api_key', 'test-key');
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => 'discover-sights']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => (string) $city->id]);
        Http::fake(['api.openai.com/v1/images/generations' => Http::response($this->fakeImage())]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/fill-missing")->assertOk()->assertJsonPath('batch.status', 'running')->assertJsonPath('batch.completed', 0);
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', 1)->assertJsonPath('batch.status', 'complete');
        $sight->refresh();
        $this->assertSame('Old description', $sight->description);
        $this->assertTrue($sight->is_featured);
        $this->assertNotEmpty($sight->image_url);
        $this->get($sight->image_url)->assertOk();
        Http::assertSentCount(1);
        $this->postJson("/admin/api/ai/{$batch}/fill-missing")->assertOk()->assertJsonPath('batch.status', 'complete')->assertJsonPath('batch.completed', 1);
        $this->postJson("/admin/api/ai/{$batch}/process")->assertJsonPath('processed', false);
        Http::assertSentCount(1);
    }

    public function test_fill_missing_repairs_a_saved_image_url_whose_file_is_missing(): void
    {
        [$sight, $batch] = $this->sightBatch();
        config()->set('services.openai.api_key', 'test-key');
        DB::table('ai_content_batches')->where('id', $batch)->update(['category' => 'countries']);
        DB::table('ai_content_items')->where('batch_id', $batch)->update(['target_id' => 'FR']);
        Country::where('code', 'FR')->update(['description' => 'Reviewed description.', 'hero_image' => '/images/countries/missing.webp']);
        Http::fake(['api.openai.com/v1/images/generations' => Http::response($this->fakeImage())]);
        $this->withHeader('X-Admin-Key', 'test-admin-key')->postJson("/admin/api/ai/{$batch}/fill-missing")->assertOk()->assertJsonPath('batch.status', 'running');
        $this->postJson("/admin/api/ai/{$batch}/process")->assertOk()->assertJsonPath('batch.completed', 1);
        $country = Country::find('FR');
        $this->assertSame('Reviewed description.', $country->description);
        $this->assertNotSame('/images/countries/missing.webp', $country->hero_image);
        $this->get($country->hero_image)->assertOk();
        Http::assertSentCount(1);
    }
}
