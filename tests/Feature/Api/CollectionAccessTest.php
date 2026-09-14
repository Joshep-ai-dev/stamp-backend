<?php

namespace Tests\Feature\Api;

use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_access_overrides_a_free_place_and_guests_only_get_a_preview(): void
    {
        $kind = CollectionKind::create(['id' => 'exclusive', 'title' => 'Exclusive', 'access' => 'pro', 'is_published' => true]);
        CollectionList::create(['id' => 'one', 'collectionkind_id' => $kind->id, 'title' => 'One', 'access' => 'free', 'detail' => 'Member content']);
        $this->getJson('/api/v1/collections/exclusive')->assertOk()
            ->assertJsonPath('access', 'pro')->assertJsonPath('places.0.isPremium', true)->assertJsonPath('places.0.content', '');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/me/completions/collection-exclusive-one', ['completed' => true])->assertForbidden();
        $this->putJson('/api/v1/me/collections/exclusive', ['progress' => 100])->assertForbidden();
        $this->postJson('/api/v1/me/sync/travel-state', ['completedSightIds' => ['collection-exclusive-one']])->assertForbidden();
        $this->assertDatabaseCount('completions', 0);
        $user->forceFill(['plan' => 'pro'])->save();
        $this->getJson('/api/v1/collections/exclusive')->assertOk()->assertJsonPath('places.0.content', 'Member content');
        $this->putJson('/api/v1/me/completions/collection-exclusive-one', ['completed' => true])->assertOk();
    }

    public function test_everyone_collection_allows_free_places_but_preserves_place_restrictions(): void
    {
        $kind = CollectionKind::create(['id' => 'open', 'title' => 'Open', 'access' => 'free', 'is_published' => true]);
        CollectionList::create(['id' => 'free', 'collectionkind_id' => $kind->id, 'title' => 'Free', 'access' => 'free']);
        CollectionList::create(['id' => 'paid', 'collectionkind_id' => $kind->id, 'title' => 'Paid', 'access' => 'pro']);
        $this->getJson('/api/v1/collections/open')->assertOk()->assertJsonPath('access', 'free');
        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/v1/me/completions/collection-open-free', ['completed' => true])->assertOk();
        $this->putJson('/api/v1/me/completions/collection-open-paid', ['completed' => true])->assertForbidden();
    }
}
