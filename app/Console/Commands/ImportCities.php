<?php

namespace App\Console\Commands;

use App\Models\CatalogVersion;
use App\Models\City;
use App\Models\Country;
use App\Services\CountryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;
use Throwable;

class ImportCities extends Command
{
    protected $signature = 'cities:import {path} {--dataset-version=} {--force} {--prune} {--max-rejected=0}';

    protected $description = 'Stream and idempotently import the private world cities CSV catalog';

    public function handle(CountryResolver $resolver): int
    {
        $path = $this->resolvePath($this->argument('path'));
        if (! is_readable($path)) {
            $this->error("CSV is not readable: {$path}");

            return self::FAILURE;
        }
        $checksum = hash_file('sha256', $path);
        $existing = CatalogVersion::where('checksum', $checksum)->first();
        if ($existing && ! $this->option('force')) {
            $this->info("Already imported checksum {$checksum} (version {$existing->version}).");

            return self::SUCCESS;
        }
        $file = new SplFileObject($path, 'r');
        $file->setCsvControl(',', '"', '');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $header = array_map(fn ($value) => trim((string) $value), $file->fgetcsv());
        $columns = array_flip($header);
        $simpleMaps = isset($columns['city'], $columns['iso2'], $columns['id']);
        if (! $simpleMaps && $header !== ['name', 'country', 'subcountry', 'geonameid']) {
            $this->error('Invalid CSV header. Expected the legacy GeoNames or current SimpleMaps world-cities schema.');

            return self::FAILURE;
        }
        $now = now();
        $chunk = [];
        $seen = [];
        $countries = [];
        $processed = 0;
        $rejected = 0;
        $updated = 0;
        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if ($row === [null] || $row === false) {
                continue;
            } try {
                if (count($row) !== count($header)) throw new RuntimeException('wrong column count');
                $value = fn (string $key): string => trim((string) ($row[$columns[$key]] ?? ''));
                $name = $value($simpleMaps ? 'city' : 'name');
                $countryName = $value('country');
                $subcountry = $value($simpleMaps ? 'admin_name' : 'subcountry');
                $geonameId = $value($simpleMaps ? 'id' : 'geonameid');
                if ($simpleMaps && $geonameId === '') $geonameId = 'coord:'.$value('lat').':'.$value('lng');
                if ($name === '' || $countryName === '' || $geonameId === '' || (! $simpleMaps && ! ctype_digit($geonameId))) {
                    throw new RuntimeException('required field invalid');
                }
                $datasetCode = ['XG' => 'PS', 'XW' => 'PS', 'XR' => 'SJ'][$value('iso2')] ?? $value('iso2');
                $country = $resolver->resolve($simpleMaps ? $datasetCode : $countryName);
                $countries[$country['code']] = array_merge($country, ['normalized_name' => $this->normalize($country['name']), 'created_at' => $now, 'updated_at' => $now]);
                $chunk[] = ['geoname_id' => $geonameId, 'name' => $name, 'ascii_name' => $simpleMaps ? ($value('city_ascii') ?: null) : null, 'normalized_name' => $this->normalize($name), 'country_code' => $country['code'], 'iso3' => $simpleMaps ? ($value('iso3') ?: null) : null, 'subcountry' => $subcountry ?: null, 'normalized_subcountry' => $subcountry ? $this->normalize($subcountry) : null, 'latitude' => $simpleMaps && is_numeric($value('lat')) ? $value('lat') : null, 'longitude' => $simpleMaps && is_numeric($value('lng')) ? $value('lng') : null, 'population' => $simpleMaps && is_numeric($value('population')) ? (int) $value('population') : null, 'capital' => $simpleMaps ? ($value('capital') ?: null) : null, 'created_at' => $now, 'updated_at' => $now];
                if ($this->option('prune')) {
                    $seen[] = $geonameId;
                } $processed++;
                if (count($chunk) >= 750) {
                    $updated += $this->flush($countries, $chunk);
                    $countries = [];
                    $chunk = [];
                }
            } catch (Throwable $e) {
                $rejected++;
                $this->warn("Rejected CSV line {$file->key()}: {$e->getMessage()}");
                if ($rejected > (int) $this->option('max-rejected')) {
                    $this->error('Rejected-row threshold exceeded; import aborted.');

                    return self::FAILURE;
                }
            }
        }
        if ($chunk) {
            $updated += $this->flush($countries, $chunk);
        } if ($this->option('prune')) {
            // Replace obsolete catalog rows while retaining legacy cities that
            // are still referenced by user or editorial content.
            City::whereNotIn('geoname_id', $seen)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('visits')->whereColumn('visits.city_id', 'cities.id'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('sights')->whereColumn('sights.city_id', 'cities.id'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('collectionlist')->whereColumn('collectionlist.city_id', 'cities.id'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('daily_destinations')->whereColumn('daily_destinations.city_id', 'cities.id'))
                ->delete();
        }
        CatalogVersion::updateOrCreate(['checksum' => $checksum], ['dataset' => basename($path), 'version' => $this->option('dataset-version') ?: now()->toDateString(), 'row_count' => $processed, 'imported_at' => $now]);
        Cache::forget('catalog:countries');
        Cache::forget('catalog:version');
        $this->info("Imported {$processed} cities; affected {$updated}, rejected {$rejected} rows.");
        $this->info('Dataset version: '.($this->option('dataset-version') ?: now()->toDateString()));

        return self::SUCCESS;
    }

    private function flush(array $countries, array $cities): int
    {
        DB::transaction(function () use ($countries, $cities) {
            Country::upsert(array_values($countries), ['code'], ['name', 'normalized_name', 'continent_code', 'flag', 'updated_at']);
            City::upsert($cities, ['geoname_id'], ['name', 'ascii_name', 'normalized_name', 'country_code', 'iso3', 'subcountry', 'normalized_subcountry', 'latitude', 'longitude', 'population', 'capital', 'updated_at']);
        });

        return count($cities);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }

    private function resolvePath(string $path): string
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
