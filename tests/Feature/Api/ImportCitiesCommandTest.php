<?php

namespace Tests\Feature\Api;

use App\Models\CatalogVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportCitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplied_catalog_imports_and_is_idempotent(): void
    {
        $path = storage_path('app/imports/world-cities.csv');

        $this->artisan('cities:import', ['path' => $path, '--dataset-version' => '2026-08-04'])
            ->assertSuccessful();

        $this->assertDatabaseCount('cities', 50250);
        $this->assertSame(50250, CatalogVersion::firstOrFail()->row_count);
        $this->assertDatabaseHas('cities', [
            'name' => 'San Francisco',
            'country_code' => 'US',
            'subcountry' => 'California',
            'population' => 3417736,
        ]);
        $this->assertDatabaseHas('cities', [
            'name' => 'Cameron Highlands',
            'country_code' => 'MY',
            'subcountry' => 'Pahang',
        ]);

        $this->artisan('cities:import', ['path' => $path, '--dataset-version' => '2026-08-04'])
            ->expectsOutputToContain('Already imported checksum')
            ->assertSuccessful();
        $this->assertDatabaseCount('catalog_versions', 1);
    }
}
