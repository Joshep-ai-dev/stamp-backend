<?php

namespace App\Http\Controllers;

use App\Services\KrooScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = KrooScore::for($user);
        $visits = $user->visits;
        $continentCounts = collect(['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'])->mapWithKeys(fn ($code) => [$code => $visits->where('continent_code', $code)->pluck('country_code')->unique()->count()]);

        return response()->json([
            'counts' => collect($summary['counts'])->except('collections')->all(),
            'score' => $summary['score'], 'level' => $summary['level'], 'challengePoints' => $summary['challengePoints'],
            'worldProgress' => (int) round($summary['counts']['countries'] / 195 * 100), 'visitedCountryCodes' => $summary['visitedCountryCodes'],
            'continentCounts' => $continentCounts, 'updatedAt' => now()->utc()->toISOString(),
        ]);
    }
}
