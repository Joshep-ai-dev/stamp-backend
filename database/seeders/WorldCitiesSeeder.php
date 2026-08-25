<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class WorldCitiesSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/imports/world-cities.csv');
        if (! is_file($path)) {
            throw new RuntimeException('The storage/app/imports/world-cities.csv catalog is missing.');
        }
        $result = Artisan::call('cities:import', ['path' => $path, '--dataset-version' => 'reference']);
        if ($result !== 0) {
            throw new RuntimeException('Could not import world-cities.csv: '.Artisan::output());
        }
    }
}
