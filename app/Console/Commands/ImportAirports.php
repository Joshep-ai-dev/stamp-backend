<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ImportAirports extends Command
{
    private const SOURCE = 'https://raw.githubusercontent.com/mwgg/Airports/master/airports.json';

    protected $signature = 'airports:import {--source='.self::SOURCE.'} {--prune}';
    protected $description = 'Import the mwgg/Airports JSON catalog into the airports table';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $json = filter_var($source, FILTER_VALIDATE_URL)
            ? Http::timeout(120)->get($source)->throw()->body()
            : file_get_contents($source);
        if (! is_string($json)) throw new RuntimeException("Airport source is not readable: {$source}");
        $catalog = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $now = now(); $rows = []; $seen = [];
        foreach ($catalog as $key => $item) {
            $icao = strtoupper(trim((string) ($item['icao'] ?? $key)));
            $country = strtoupper(trim((string) ($item['country'] ?? '')));
            $lat = $item['lat'] ?? null; $lon = $item['lon'] ?? null;
            if (! preg_match('/^[A-Z0-9]{1,8}$/', $icao) || strlen($country) !== 2 || ! is_numeric($lat) || ! is_numeric($lon)) continue;
            $city = trim((string) ($item['city'] ?? '')) ?: null;
            $state = trim((string) ($item['state'] ?? '')) ?: null;
            $rows[] = [
                'source_id' => $this->sourceId($icao), 'icao_code' => $icao,
                'iata_code' => strtoupper(trim((string) ($item['iata'] ?? ''))) ?: null,
                'name' => trim((string) ($item['name'] ?? $icao)),
                'municipality' => $city ?? '', 'city' => $city,
                'normalized_city' => $city ? $this->normalize($city) : null, 'state' => $state,
                'normalized_state' => $state ? $this->normalize($state) : null, 'country_code' => $country,
                'latitude' => (float) $lat, 'longitude' => (float) $lon,
                'elevation' => is_numeric($item['elevation'] ?? null) ? (int) $item['elevation'] : null,
                'timezone' => trim((string) ($item['tz'] ?? '')) ?: null, 'created_at' => $now, 'updated_at' => $now,
            ];
            $seen[] = $icao;
            if (count($rows) >= 750) { $this->flush($rows); $rows = []; }
        }
        if ($rows) $this->flush($rows);
        if ($this->option('prune')) Airport::whereNotIn('icao_code', $seen)->delete();
        $this->info('Imported '.count($seen).' airports.');
        return self::SUCCESS;
    }

    private function flush(array $rows): void
    {
        Airport::upsert($rows, ['icao_code'], ['iata_code', 'name', 'municipality', 'normalized_city', 'city', 'normalized_state', 'state', 'country_code', 'latitude', 'longitude', 'elevation', 'timezone', 'updated_at']);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }

    private function sourceId(string $icao): int
    {
        // Preserve leading zeroes by reserving a separate base-36 range for
        // each code length. The largest possible value easily fits BIGINT.
        return strlen($icao) * 2_821_109_907_456 + intval($icao, 36);
    }
}
