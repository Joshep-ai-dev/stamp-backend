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

        $this->assertDatabaseCount('cities', 34065);
        $this->assertSame(34065, CatalogVersion::firstOrFail()->row_count);

        $this->artisan('cities:import', ['path' => $path, '--dataset-version' => '2026-08-04'])
            ->expectsOutputToContain('Already imported checksum')
            ->assertSuccessful();
        $this->assertDatabaseCount('catalog_versions', 1);
    }
}
