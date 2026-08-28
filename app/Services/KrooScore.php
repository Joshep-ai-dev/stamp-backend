<?php

namespace App\Services;

use App\Models\User;

class KrooScore
{
    public static function for(User $user): array
    {
        $user->loadMissing(['visits', 'completions', 'rewards', 'collectionProgress']);
        $visits = $user->visits;
        $continents = $visits->pluck('continent_code')->filter()->unique();
        $countries = $visits->pluck('country_code')->filter()->unique()->sort()->values();
        $cities = $visits->pluck('city_id')->filter()->unique();
        $places = $visits->flatMap(fn ($visit) => $visit->places ?? []);
        $airports = $places->where('type', 'airport')->pluck('id')->unique();
        $sights = $places->where('type', 'sight')->pluck('id')->merge($user->completions->pluck('sight_id'))->unique();
        $challengePoints = min(6.25, (float) $user->rewards->where('unlocked', true)->sum('kroo_points'));
        $score = min(100, min(7, $continents->count()) + min(48.75, $countries->count() * .25) + min(10, $cities->count() * .005) + min(8, $airports->count() * .01) + min(20, $sights->count() * .002) + $challengePoints);

        return [
            'score' => round($score, 3),
            'level' => self::level($score),
            'challengePoints' => round($challengePoints, 3),
            'visitedCountryCodes' => $countries,
            'counts' => [
                'continents' => $continents->count(), 'countries' => $countries->count(),
                'cities' => $cities->count(), 'airports' => $airports->count(), 'sights' => $sights->count(),
                'collections' => $user->collectionProgress->where('progress', 100)->count(),
            ],
        ];
    }

    private static function level(float $score): string
    {
        return match (true) {
            $score >= 100 => 'Kroo Legend',
            $score >= 75 => 'Kroo Master',
            $score >= 50 => 'Voyager',
            $score >= 30 => 'Wayfarer',
            $score >= 15 => 'Explorer',
            $score >= 5 => 'Traveler',
            default => 'Wanderer',
        };
    }
}
