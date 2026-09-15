<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\User;
use App\Services\KrooId;
use App\Services\KrooScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function leaderboard(Request $request): JsonResponse
    {
        $scope = $request->validate(['scope' => ['sometimes', 'in:global,friends']])['scope'] ?? 'global';
        if ($scope === 'friends') {
            abort_unless($request->user(), 401, 'Sign in to view friends.');
        }
        $query = User::query();
        if ($scope === 'friends') {
            $ids = Friend::where('user_id', $request->user()->id)->pluck('friend_id')->merge(Friend::where('friend_id', $request->user()->id)->pluck('user_id'))->push($request->user()->id);
            $query->whereIn('id', $ids);
        }
        $items = $query->with(['visits', 'completions', 'rewards', 'collectionProgress'])->get()->map(fn ($user) => $this->profile($user))->sortByDesc('score')->values()->take(10);

        return response()->json($items);
    }

    public function friendCode(Request $request): JsonResponse
    {
        return response()->json(['code' => KrooId::format($request->user()->kroo_id)]);
    }

    public function scan(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string']])['code'];
        preg_match('#^stampo://friend/([^/?\#]+)$#', $code, $match);
        $value = $match[1] ?? $code;
        $numeric = preg_replace('/^KROO-/i', '', trim($value));
        $friend = User::where('friend_code', $value)
            ->when(ctype_digit($numeric), fn ($query) => $query->orWhere('kroo_id', (int) $numeric))
            ->first();
        abort_unless($friend, 422, 'This is not a valid Stampo friend code.');
        abort_if($friend->is($request->user()), 422, 'You cannot add your own friend code.');
        $first = min($request->user()->id, $friend->id);
        $second = max($request->user()->id, $friend->id);
        Friend::firstOrCreate(['user_id' => $first, 'friend_id' => $second]);

        return response()->json($this->profile($friend->load(['visits', 'completions', 'rewards', 'collectionProgress'])));
    }

    private function profile(User $user): array
    {
        $summary = KrooScore::for($user);
        $counts = $summary['counts'];

        return [
            'id' => $user->id, 'name' => $user->name, 'photoUri' => $user->photo_uri,
            'plan' => $user->plan, 'score' => $summary['score'], 'level' => $summary['level'],
            'stats' => ['countries' => $counts['countries'], 'continents' => $counts['continents'], 'cities' => $counts['cities'], 'collections' => $counts['collections']],
            'countries' => $counts['countries'], 'continents' => $counts['continents'], 'cities' => $counts['cities'],
            'collections' => $counts['collections'], 'sights' => $counts['sights'],
        ];
    }
}
