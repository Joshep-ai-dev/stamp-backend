<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ImportAirports extends Command
{
    public const SOURCE = 'storage/app/imports/airports.csv';

    protected $signature = 'airports:import {--source='.self::SOURCE.'} {--regions=storage/app/imports/regions.csv}';

    protected $description = 'Replace the airport catalog from the saved OurAirports CSV files';

    public function handle(): int
    {
        $source = $this->path((string) $this->option('source'));
        $regions = $this->regions($this->path((string) $this->option('regions')));
        $handle = fopen($source, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Airport source is not readable: {$source}");
        }

        $header = fgetcsv($handle, escape: '');
        if (! is_array($header)) {
            throw new RuntimeException('OurAirports returned an invalid CSV catalog.');
        }

        $countryCodes = DB::table('countries')->pluck('code')
            ->mapWithKeys(fn (string $code): array => [strtoupper(trim($code)) => true])->all();
        if ($countryCodes === []) {
            throw new RuntimeException('No countries exist; import the country catalog first.');
        }

        $now = now();
        $rows = [];
        $seenIata = [];
        $skipped = 0;
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($values) !== count($header)) {
                $skipped++;

                continue;
            }
            $item = array_combine($header, $values);
            $iata = strtoupper(trim((string) ($item['iata_code'] ?? '')));
            $icao = strtoupper(trim((string) (($item['icao_code'] ?? '') ?: ($item['gps_code'] ?? '') ?: ($item['ident'] ?? ''))));
            $country = strtoupper(trim((string) ($item['iso_country'] ?? '')));
            $latitude = $item['latitude_deg'] ?? null;
            $longitude = $item['longitude_deg'] ?? null;
            if (! preg_match('/^[A-Z0-9]{3}$/', $iata) || isset($seenIata[$iata])
                || ! preg_match('/^[A-Z0-9-]{1,8}$/', $icao) || ! isset($countryCodes[$country])
                || ! is_numeric($latitude) || ! is_numeric($longitude)) {
                $skipped++;

                continue;
            }

            $municipality = trim((string) ($item['municipality'] ?? '')) ?: null;
            $regionCode = strtoupper(trim((string) ($item['iso_region'] ?? '')));
            $state = $regions[$regionCode] ?? ($regionCode ?: null);
            $rows[] = [
                'source_id' => (int) $item['id'], 'icao_code' => $icao, 'iata_code' => $iata,
                'name' => trim((string) ($item['name'] ?? '')) ?: $icao,
                'municipality' => $municipality ?? '',
                'normalized_municipality' => $municipality ? $this->normalize($municipality) : '',
                'city' => $municipality, 'normalized_city' => $municipality ? $this->normalize($municipality) : null,
                'state' => $state, 'normalized_state' => $state ? $this->normalize($state) : null,
                'country_code' => $country, 'latitude' => (float) $latitude, 'longitude' => (float) $longitude,
                'elevation' => is_numeric($item['elevation_ft'] ?? null) ? (int) $item['elevation_ft'] : null,
                'timezone' => null, 'created_at' => $now, 'updated_at' => $now,
            ];
            $seenIata[$iata] = true;
        }
        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('OurAirports produced no valid IATA-coded airports.');
        }

        DB::transaction(function () use ($rows): void {
            Airport::query()->delete();
            foreach (array_chunk($rows, 750) as $chunk) {
                Airport::insert($chunk);
            }
        });

        $this->info('Imported '.count($rows).' IATA-coded airports from OurAirports; skipped '.$skipped.' records.');

        return self::SUCCESS;
    }

    private function regions(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle, escape: '');
        $regions = [];
        while (is_array($header) && ($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($values) !== count($header)) {
                continue;
            }
            $item = array_combine($header, $values);
            $code = strtoupper(trim((string) ($item['code'] ?? '')));
            $name = trim((string) ($item['name'] ?? ''));
            if ($code !== '' && $name !== '') {
                $regions[$code] = $name;
            }
        }
        fclose($handle);

        return $regions;
    }

    private function path(string $path): string
    {
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
