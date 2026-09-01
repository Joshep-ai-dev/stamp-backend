<?php

namespace App\Services;

use App\Models\Airport;

class NearbyCatalogLookup
{
    public function find(float $latitude, float $longitude, int $radius = 3000): array
    {
        $latitudeDelta = $radius / 111320;
        $longitudeDelta = $radius / max(1, 111320 * cos(deg2rad($latitude)));

        return Airport::query()
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->get()
            ->map(function (Airport $airport) use ($latitude, $longitude): array {
                return [
                    'id' => $airport->iata_code ?: $airport->icao_code, 'type' => 'airport',
                    'name' => $airport->name, 'city' => $airport->city,
                    'countryCode' => $airport->country_code,
                    'distanceMeters' => (int) round($this->distanceMeters($latitude, $longitude, $airport->latitude, $airport->longitude)),
                    'latitude' => $airport->latitude, 'longitude' => $airport->longitude,
                    'iataCode' => $airport->iata_code, 'icaoCode' => $airport->icao_code,
                ];
            })
            ->filter(fn (array $airport): bool => $airport['distanceMeters'] <= $radius)
            ->sortBy('distanceMeters')->values()->all();
    }

    private function distanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $latDelta = deg2rad($lat2 - $lat1); $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
