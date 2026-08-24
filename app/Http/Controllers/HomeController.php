<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $visits = $user->visits()->get();
        $continents = $visits->pluck('continent_code')->filter()->unique();
        $countries = $visits->pluck('country_code')->filter()->unique()->sort()->values();
        $cities = $visits->pluck('city_id')->unique();
        $places = $visits->flatMap(fn ($visit) => $visit->places ?? []);
        $airports = $places->where('type', 'airport')->pluck('id')->unique();
        $sights = $places->where('type', 'sight')->pluck('id')->merge($user->completions()->pluck('sight_id'))->unique();
        $challengePoints = min(6.25, (float) $user->rewards()->where('unlocked', true)->sum('kroo_points'));
        $score = min(100, min(7, $continents->count()) + min(48.75, $countries->count() * .25) + min(10, $cities->count() * .005) + min(8, $airports->count() * .01) + min(20, $sights->count() * .002) + $challengePoints);
        $continentCounts = collect(['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'])->mapWithKeys(fn ($code) => [$code => $visits->where('continent_code', $code)->pluck('country_code')->unique()->count()]);

        return response()->json([
            'counts' => ['continents' => $continents->count(), 'countries' => $countries->count(), 'cities' => $cities->count(), 'airports' => $airports->count(), 'sights' => $sights->count()],
            'score' => round($score, 3), 'level' => $this->level($score), 'challengePoints' => round($challengePoints, 3),
            'worldProgress' => (int) round($countries->count() / 195 * 100), 'visitedCountryCodes' => $countries,
            'continentCounts' => $continentCounts, 'updatedAt' => now()->utc()->toISOString(),
        ]);
    }

    private function level(float $score): string
    {
        return match (true) {
            $score >= 75 => 'Kroo Master', $score >= 50 => 'Voyager', $score >= 30 => 'Wayfarer', $score >= 15 => 'Explorer', $score >= 5 => 'Traveler', default => 'Wanderer'
        };
    }
}
