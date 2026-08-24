<?php

namespace App\Http\Controllers;

use App\Models\CollectionProgress;
use App\Services\CollectionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelStateController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $rewards = $user->rewards()->get()->map(fn ($reward) => [
            'id' => $reward->id, 'userId' => $reward->user_id, 'title' => $reward->title,
            'krooPoints' => $reward->kroo_points, 'unlocked' => $reward->unlocked,
        ]);

        return response()->json([
            'completedSightIds' => $user->completions()->pluck('sight_id')->values(),
            'wishlistIds' => $user->wishlists()->pluck('target_id')->values(),
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
        abort_unless(isset(CollectionCatalog::ITEMS[$collectionId]), 404);
        $progress = $request->validate(['progress' => ['required', 'integer', 'between:0,100']])['progress'];
        $record = $request->user()->collectionProgress()->updateOrCreate(['collection_id' => $collectionId], ['progress' => $progress]);

        return response()->json($this->collection($collectionId, $record));
    }

    public function completion(Request $request, string $sightId): JsonResponse
    {
        $completed = $request->boolean('completed', true);
        if ($completed) {
            $request->user()->completions()->firstOrCreate(['sight_id' => $sightId], ['completed_at' => now()]);
        } else {
            $request->user()->completions()->where('sight_id', $sightId)->delete();
        }

        return response()->json(['sightId' => $sightId, 'completed' => $completed]);
    }

    public function wishlist(Request $request, string $targetId): JsonResponse
    {
        $saved = $request->boolean('saved', true);
        if ($saved) {
            $request->user()->wishlists()->firstOrCreate(['target_id' => $targetId], ['saved_at' => now()]);
        } else {
            $request->user()->wishlists()->where('target_id', $targetId)->delete();
        }

        return response()->json(['targetId' => $targetId, 'saved' => $saved]);
    }

    public function plan(Request $request): JsonResponse
    {
        $plan = $request->validate(['plan' => ['required', 'in:free,pro']])['plan'];
        $request->user()->update(['plan' => $plan]);

        return response()->json(['plan' => $plan]);
    }

    private function collectionItems($progress): array
    {
        return collect(CollectionCatalog::ITEMS)->map(fn ($definition, $id) => $this->collection($id, $progress->firstWhere('collection_id', $id)))->values()->all();
    }

    private function collection(string $id, ?CollectionProgress $progress): array
    {
        $value = $progress?->progress ?? 0;
        $item = ['id' => $id, ...CollectionCatalog::ITEMS[$id], 'progress' => $value, 'status' => $value === 100 ? 'completed' : 'active'];
        if ($progress) {
            $item['updatedAt'] = $progress->updated_at->utc()->toISOString();
        }

        return $item;
    }
}
