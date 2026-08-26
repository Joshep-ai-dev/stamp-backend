<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\User;
use App\Services\KrooScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        if (! $request->user()->friend_code) {
            $request->user()->update(['friend_code' => Str::random(36)]);
        }

        return response()->json(['code' => 'stampo://friend/'.$request->user()->fresh()->friend_code]);
    }

    public function scan(Request $request): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string']])['code'];
        preg_match('#^stampo://friend/([^/?\#]+)$#', $code, $match);
        $friend = isset($match[1]) ? User::where('friend_code', $match[1])->first() : null;
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
