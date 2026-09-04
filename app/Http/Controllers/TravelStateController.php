<?php

namespace App\Http\Controllers;

use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\CollectionProgress;
use App\Models\Sight;
use App\Services\CollectionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TravelStateController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'completedSightIds' => ['present', 'array', 'max:5000'],
            'completedSightIds.*' => ['string', 'max:255'],
        ]);

        foreach (array_unique($data['completedSightIds']) as $sightId) {
            $syncRequest = Request::create('/', 'PUT', ['completed' => true]);
            $syncRequest->setUserResolver(fn () => $request->user());
            $this->completion($syncRequest, $sightId);
        }
        return $this->show($request);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $rewards = $user->rewards()->get()->map(fn ($reward) => [
            'id' => $reward->id, 'userId' => $reward->user_id, 'title' => $reward->title,
            'krooPoints' => $reward->kroo_points, 'unlocked' => $reward->unlocked,
        ]);

        return response()->json([
            'completedSightIds' => $user->completions()->pluck('sight_id')->values(),
            'rewards' => $rewards,
            'challengePoints' => round(min(6.25, $rewards->where('unlocked', true)->sum('krooPoints')), 3),
            'collections' => $this->collectionItems($user->collectionProgress()->get()),
            'plan' => $user->plan,
        ]);
    }

    public function collections(Request $request): JsonResponse
    {
        $status = $request->validate(['status' => ['sometimes', 'in:all,active,completed']])['status'] ?? 'all';
        $items = collect($this->collectionItems($request->user()->collectionProgress()->get()));
        if ($status !== 'all') {
            $items = $items->where('status', $status)->values();
        }

        return response()->json($items);
    }

    public function updateCollection(Request $request, string $collectionId): JsonResponse
    {
        abort_unless(isset(CollectionCatalog::ITEMS[$collectionId]) || CollectionKind::where('is_published', true)->whereKey($collectionId)->exists(), 404);
        $progress = $request->validate(['progress' => ['required', 'integer', 'between:0,100']])['progress'];
        $record = $request->user()->collectionProgress()->updateOrCreate(['collection_id' => $collectionId], ['progress' => $progress]);

        return response()->json($this->collection($collectionId, $record));
    }

    public function completion(Request $request, string $sightId): JsonResponse
    {
        $completed = $request->boolean('completed', true);
        DB::transaction(function () use ($request, $sightId, $completed): void {
            [$city, $place] = $this->completionPlace($sightId);
            if ($completed) {
                $request->user()->completions()->firstOrCreate(['sight_id' => $sightId], ['completed_at' => now()]);
                if ($city) {
                    $visit = $request->user()->visits()->where('city_id', $city->id)->lockForUpdate()->first();
                    if (! $visit) {
                        $city->loadMissing('country');
                        $visit = $request->user()->visits()->create([
                            'city_id' => $city->id, 'city_name' => $city->name,
                            'country' => $city->country->name, 'country_code' => $city->country_code,
                            'continent_code' => $city->country->continent_code, 'subcountry' => $city->subcountry,
                            'visited_at' => now()->toDateString(), 'note' => null, 'places' => [],
                        ]);
                    }
                    $places = collect($visit->places ?? [])->reject(fn ($item) => ($item['id'] ?? null) === $sightId)->push($place)->values()->all();
                    $visit->update(['places' => $places]);
                }
            } else {
                $request->user()->completions()->where('sight_id', $sightId)->delete();
                $request->user()->visits()->get()->each(function ($visit) use ($sightId): void {
                    $places = collect($visit->places ?? []);
                    $updated = $places->reject(fn ($item) => ($item['id'] ?? null) === $sightId)->values();
                    if ($updated->count() !== $places->count()) {
                        $visit->update(['places' => $updated->all()]);
                    }
                });
            }
        });

        return response()->json(['sightId' => $sightId, 'completed' => $completed]);
    }

    private function completionPlace(string $targetId): array
    {
        if (! str_starts_with($targetId, 'collection-')) {
            $sight = Sight::with('city.country')->find($targetId);
            if ($sight) {
                return [$sight->city, ['id' => $targetId, 'name' => $sight->name, 'type' => 'sight']];
            }
        }

        $list = CollectionList::with(['kinds', 'city.country'])->get()->first(
            fn ($item) => $item->kinds->contains(
                fn ($kind) => "collection-{$kind->id}-{$item->id}" === $targetId,
            ),
        );

        $city = $list && $list->kinds->contains('id', 'seas') ? null : $list?->city;

        return [$city, ['id' => $targetId, 'name' => $list?->title ?? $targetId, 'type' => 'sight']];
    }

    public function plan(Request $request): JsonResponse
    {
        $plan = $request->validate(['plan' => ['required', 'in:free,pro']])['plan'];
        $request->user()->update(['plan' => $plan]);

        return response()->json(['plan' => $plan]);
    }

    private function collectionItems($progress): array
    {
        $managed = CollectionKind::with('lists.city.country')->where('is_published', true)->orderBy('title')->get();
        $legacy = collect(CollectionCatalog::ITEMS)
            ->except($managed->pluck('id'))
            ->map(fn ($definition, $id) => $this->collection($id, $progress->firstWhere('collection_id', $id)));

        return $managed->map(fn ($definition) => $this->collection($definition->id, $progress->firstWhere('collection_id', $definition->id), $definition))->concat($legacy)->values()->all();
    }

    private function collection(string $id, ?CollectionProgress $progress, ?CollectionKind $definition = null): array
    {
        $value = $progress?->progress ?? 0;
        $legacy = CollectionCatalog::ITEMS[$id] ?? [];
        $item = ['id' => $id, 'title' => $definition?->title ?? $legacy['title'], 'detail' => $definition?->detail ?? $legacy['detail'], 'imageUrl' => $definition?->image, 'places' => $definition?->lists?->sortBy('title')->values()->map(fn ($list) => ['id' => $list->id, 'name' => $list->title, 'imageUrl' => $list->image, 'location' => $list->location, 'detail' => $list->detail, 'access' => $list->access, 'isPremium' => $list->access === 'pro']) ?? [], 'progress' => $value, 'status' => $value === 100 ? 'completed' : 'active'];
        if ($progress) {
            $item['updatedAt'] = $progress->updated_at->utc()->toISOString();
        }

        return $item;
    }
}
