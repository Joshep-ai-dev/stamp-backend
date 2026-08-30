<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SplFileObject;

class ImportAirports extends Command
{
    protected $signature = 'airports:import {source=https://davidmegginson.github.io/ourairports-data/airports.csv}';

    protected $description = 'Import scheduled airports with IATA codes from the public-domain OurAirports CSV';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $path = $source;
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            $path = storage_path('app/imports/airports.csv');
            if (! is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
            $this->info('Downloading the current OurAirports dataset...');
            Http::timeout(120)->sink($path)->get($source)->throw();
        } elseif (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }
        if (! is_readable($path)) {
            $this->error("CSV is not readable: {$path}");
            return self::FAILURE;
        }

        $file = new SplFileObject($path, 'r');
        $file->setCsvControl(',', '"', '');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $columns = array_flip(array_map(fn ($value) => trim((string) $value), $file->fgetcsv()));
        $required = ['id', 'name', 'iso_country', 'municipality', 'scheduled_service', 'iata_code', 'icao_code', 'latitude_deg', 'longitude_deg'];
        if (array_diff($required, array_keys($columns))) {
            $this->error('The CSV is not a supported OurAirports airports.csv file.');
            return self::FAILURE;
        }

        $now = now();
        $countryCodes = DB::table('countries')->pluck('code')->flip();
        $rows = [];
        $count = 0;
        DB::table('airports')->delete();
        while (! $file->eof()) {
            $row = $file->fgetcsv();
            if ($row === false || $row === [null]) continue;
            $value = fn (string $key): string => trim((string) ($row[$columns[$key]] ?? ''));
            if ($value('scheduled_service') !== 'yes' || $value('iata_code') === '' || $value('municipality') === '' || ! $countryCodes->has(strtoupper($value('iso_country')))) continue;
            $rows[] = ['source_id' => (int) $value('id'), 'name' => $value('name'), 'country_code' => strtoupper($value('iso_country')), 'municipality' => $value('municipality'), 'normalized_municipality' => Str::of($value('municipality'))->ascii()->lower()->squish()->toString(), 'iata_code' => strtoupper($value('iata_code')), 'icao_code' => $value('icao_code') ?: null, 'latitude' => is_numeric($value('latitude_deg')) ? $value('latitude_deg') : null, 'longitude' => is_numeric($value('longitude_deg')) ? $value('longitude_deg') : null, 'created_at' => $now, 'updated_at' => $now];
            if (count($rows) >= 500) { DB::table('airports')->insertOrIgnore($rows); $count += count($rows); $rows = []; }
        }
        if ($rows) { DB::table('airports')->insertOrIgnore($rows); $count += count($rows); }
        $this->info("Imported {$count} scheduled airports with IATA codes.");
        return self::SUCCESS;
    }
}
