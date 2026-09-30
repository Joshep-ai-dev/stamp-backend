<?php

namespace App\Services;

use App\Models\City;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

final class CityAliases
{
    public static function unique(Collection $cities): Collection
    {
        return $cities->map(fn (City $city) => City::where('geoname_id', self::canonicalId($city->geoname_id))->first() ?? $city)
            ->unique('geoname_id')->values();
    }

    public static function relatedIds(City $city): Collection
    {
        return City::where('country_code', $city->country_code)
            ->where('normalized_name', $city->normalized_name)->get()
            ->filter(fn (City $candidate) => self::canonicalId($candidate->geoname_id) === $city->geoname_id)
            ->pluck('id');
    }
    public static function canonicalId(string $id): string
    {
        $city = City::where('geoname_id', $id)->first();
        if (! $city) return $id;

        $candidates = City::where('country_code', $city->country_code)
            ->where('normalized_name', $city->normalized_name)->get();
        if ($candidates->count() < 2) return $id;
        $geocodedCount = $candidates->filter(fn (City $item) => self::hasCoordinates($item))->count();
        $matches = $candidates->filter(fn (City $item) => self::samePlace($city, $item, $geocodedCount));

        return $matches->sort(function (City $a, City $b): int {
            return self::score($b) <=> self::score($a) ?: $a->id <=> $b->id;
        })->first()?->geoname_id ?? $id;
    }

    private static function samePlace(City $a, City $b, int $geocodedCount): bool
    {
        if ($a->id === $b->id) return true;
        if (self::hasCoordinates($a) && self::hasCoordinates($b)) {
            $lat = deg2rad($b->latitude - $a->latitude);
            $lng = deg2rad($b->longitude - $a->longitude);
            $distance = 6371 * 2 * asin(min(1, sqrt(sin($lat / 2) ** 2
                + cos(deg2rad($a->latitude)) * cos(deg2rad($b->latitude)) * sin($lng / 2) ** 2)));

            return $distance <= 15;
        }
        if ($a->normalized_subcountry && $a->normalized_subcountry === $b->normalized_subcountry) return true;
        if ($geocodedCount !== 1) return false;

        foreach ([[$a, $b], [$b, $a]] as [$older, $geocoded]) {
            if (! self::hasCoordinates($older) && self::hasCoordinates($geocoded)
                && $older->normalized_subcountry === $older->normalized_name) return true;
        }

        return false;
    }

    private static function hasCoordinates(City $city): bool
    {
        return $city->latitude !== null && $city->longitude !== null
            && ((float) $city->latitude !== 0.0 || (float) $city->longitude !== 0.0);
    }

    private static function score(City $city): int
    {
        return DB::table('sights')->where('city_id', $city->id)->count() * 1000
            + DB::table('collectionlist')->where('city_id', $city->id)->count() * 100
            + DB::table('daily_destinations')->where('city_id', $city->id)->count() * 100
            + (filled($city->description) ? 50 : 0)
            + ($city->normalized_subcountry === $city->normalized_name ? 20 : 0)
            + (filled($city->image_url) ? 10 : 0)
            + DB::table('visits')->where('city_id', $city->id)->count();
    }
}
