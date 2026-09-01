<?php

namespace App\Console\Commands;

use App\Models\Airport;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ImportAirports extends Command
{
    private const SOURCE = 'https://davidmegginson.github.io/ourairports-data/airports.csv';
    private const REGIONS_SOURCE = 'https://davidmegginson.github.io/ourairports-data/regions.csv';

    protected $signature = 'airports:import
        {--source='.self::SOURCE.' : OurAirports airports.csv URL or local path}
        {--regions='.self::REGIONS_SOURCE.' : OurAirports regions.csv URL or local path}';

    protected $description = 'Synchronize all airports with an IATA code from OurAirports';

    public function handle(): int
    {
        $airportCsv = $this->readSource((string) $this->option('source'));
        $regionCsv = $this->readSource((string) $this->option('regions'));
        $regions = $this->regionNames($regionCsv);
        $countryCodes = DB::table('countries')->pluck('code')
            ->mapWithKeys(fn (string $code): array => [strtoupper($code) => true])
            ->all();

        if ($countryCodes === []) {
            throw new RuntimeException('No countries exist; import the country catalog first.');
        }

        $now = now();
        $rows = [];
        $seenIcao = [];
        $seenIata = [];
        $withoutIata = 0;
        $skipped = 0;

        DB::beginTransaction();

        try {
            // This command replaces the previous catalog. Its source IDs are
            // from a different namespace and cannot be safely upserted into
            // legacy rows that use source_id as their primary key.
            Airport::query()->delete();

            foreach ($this->csvRecords($airportCsv) as $item) {
                $iata = strtoupper(trim((string) ($item['iata_code'] ?? '')));
                if (! preg_match('/^[A-Z0-9]{3}$/', $iata)) {
                    $withoutIata++;
                    continue;
                }

                $sourceId = trim((string) ($item['id'] ?? ''));
                $icao = strtoupper(trim((string) (($item['icao_code'] ?? '') ?: ($item['gps_code'] ?? '') ?: ($item['ident'] ?? ''))));
                $country = strtoupper(trim((string) ($item['iso_country'] ?? '')));
                $latitude = $item['latitude_deg'] ?? null;
                $longitude = $item['longitude_deg'] ?? null;

                if (! ctype_digit($sourceId)
                    || ! preg_match('/^[A-Z0-9]{1,8}$/', $icao)
                    || isset($seenIcao[$icao])
                    || isset($seenIata[$iata])
                    || ! isset($countryCodes[$country])
                    || ! is_numeric($latitude)
                    || ! is_numeric($longitude)) {
                    $skipped++;
                    continue;
                }

                $seenIcao[$icao] = true;
                $seenIata[$iata] = true;
                $city = trim((string) ($item['municipality'] ?? '')) ?: null;
                $regionCode = strtoupper(trim((string) ($item['iso_region'] ?? '')));
                $state = $regions[$regionCode] ?? null;
                $normalizedCity = $city ? $this->normalize($city) : null;

                $rows[] = [
                    'source_id' => (int) $sourceId,
                    'icao_code' => $icao,
                    'iata_code' => $iata,
                    'name' => trim((string) ($item['name'] ?? '')) ?: $icao,
                    'municipality' => $city ?? '',
                    'normalized_municipality' => $normalizedCity ?? '',
                    'city' => $city,
                    'normalized_city' => $normalizedCity,
                    'state' => $state,
                    'normalized_state' => $state ? $this->normalize($state) : null,
                    'country_code' => $country,
                    'latitude' => (float) $latitude,
                    'longitude' => (float) $longitude,
                    'elevation' => is_numeric($item['elevation_ft'] ?? null) ? (int) $item['elevation_ft'] : null,
                    'timezone' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($rows) >= 750) {
                    $this->flush($rows);
                    $rows = [];
                }
            }

            if ($rows) {
                $this->flush($rows);
            }
            if ($seenIcao === []) {
                throw new RuntimeException('OurAirports source produced no valid IATA-coded airports.');
            }

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $this->info('Imported '.count($seenIcao).' IATA-coded airports; ignored '.$withoutIata.' records without an IATA code; skipped '.$skipped.' invalid or unsupported records.');

        return self::SUCCESS;
    }

    private function flush(array $rows): void
    {
        Airport::upsert($rows, ['icao_code'], [
            'iata_code', 'name', 'municipality', 'normalized_municipality',
            'normalized_city', 'city', 'normalized_state', 'state', 'country_code',
            'latitude', 'longitude', 'elevation', 'updated_at',
        ]);
    }

    private function regionNames(string $csv): array
    {
        $regions = [];
        foreach ($this->csvRecords($csv) as $region) {
            $code = strtoupper(trim((string) ($region['code'] ?? '')));
            $name = trim((string) ($region['name'] ?? ''));
            if ($code !== '' && $name !== '') {
                $regions[$code] = $name;
            }
        }

        return $regions;
    }

    private function csvRecords(string $csv): Generator
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false || fwrite($stream, $csv) === false) {
            throw new RuntimeException('Unable to buffer OurAirports CSV data.');
        }
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        if (! is_array($headers)) {
            fclose($stream);
            throw new RuntimeException('OurAirports CSV header is missing.');
        }
        $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");

        try {
            while (($values = fgetcsv($stream, escape: '')) !== false) {
                if (count($values) === count($headers)) {
                    yield array_combine($headers, $values);
                }
            }
        } finally {
            fclose($stream);
        }
    }

    private function readSource(string $source): string
    {
        $contents = filter_var($source, FILTER_VALIDATE_URL)
            ? Http::timeout(120)->get($source)->throw()->body()
            : file_get_contents($source);

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException("Airport source is not readable: {$source}");
        }

        return $contents;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
