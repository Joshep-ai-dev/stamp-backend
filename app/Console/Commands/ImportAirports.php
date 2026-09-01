<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ImportAirports extends Command
{
    public const SOURCE = 'https://raw.githubusercontent.com/mwgg/Airports/master/airports.json';

    protected $signature = 'airports:import {--source='.self::SOURCE.'}';
    protected $description = 'Replace the airport catalog with IATA-coded records from mwgg/Airports';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $contents = filter_var($source, FILTER_VALIDATE_URL)
            ? Http::timeout(120)->get($source)->throw()->body()
            : file_get_contents($source);
        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException("Airport source is not readable: {$source}");
        }

        $catalog = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($catalog)) {
            throw new RuntimeException('mwgg/Airports returned an invalid JSON catalog.');
        }

        $now = now();
        $rows = [];
        $seenIcao = [];
        $seenIata = [];

        foreach ($catalog as $key => $item) {
            if (! is_array($item)) continue;
            $icao = strtoupper(trim((string) ($item['icao'] ?? $key)));
            $iata = strtoupper(trim((string) ($item['iata'] ?? '')));
            $country = strtoupper(trim((string) ($item['country'] ?? '')));
            $latitude = $item['lat'] ?? null;
            $longitude = $item['lon'] ?? null;

            // The app intentionally stores only airports that users can select
            // by their three-character IATA code.
            if (! preg_match('/^[A-Z0-9]{3}$/', $iata)
                || ! preg_match('/^[A-Z0-9]{1,8}$/', $icao)
                || isset($seenIata[$iata]) || isset($seenIcao[$icao])
                || strlen($country) !== 2
                || ! is_numeric($latitude) || ! is_numeric($longitude)) continue;

            $city = trim((string) ($item['city'] ?? '')) ?: null;
            $state = trim((string) ($item['state'] ?? '')) ?: null;
            $rows[] = [
                'source_id' => strlen($icao) * 2_821_109_907_456 + intval($icao, 36),
                'icao_code' => $icao, 'iata_code' => $iata,
                'name' => trim((string) ($item['name'] ?? '')) ?: $icao,
                'municipality' => $city ?? '',
                'normalized_municipality' => $city ? $this->normalize($city) : '',
                'city' => $city, 'normalized_city' => $city ? $this->normalize($city) : null,
                'state' => $state, 'normalized_state' => $state ? $this->normalize($state) : null,
                'country_code' => $country, 'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'elevation' => is_numeric($item['elevation'] ?? null) ? (int) $item['elevation'] : null,
                'timezone' => trim((string) ($item['tz'] ?? '')) ?: null,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $seenIata[$iata] = true;
            $seenIcao[$icao] = true;
        }

        if ($rows === []) {
            throw new RuntimeException('mwgg/Airports produced no valid IATA-coded airports.');
        }

        DB::transaction(function () use ($rows): void {
            Airport::query()->delete();
            foreach (array_chunk($rows, 750) as $chunk) {
                Airport::insert($chunk);
            }
        });

        $this->info('Imported '.count($rows).' IATA-coded airports from mwgg/Airports.');
        return self::SUCCESS;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
