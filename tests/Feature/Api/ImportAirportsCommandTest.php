<?php

namespace Tests\Feature\Api;

use App\Console\Commands\ImportAirports;
use App\Models\Airport;
use App\Services\AirportLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportAirportsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_iata_airports_and_resolves_both_istanbul_airports(): void
    {
        Http::fake([ImportAirports::SOURCE => Http::response([
            'LTFM' => ['icao' => 'LTFM', 'iata' => 'IST', 'name' => 'Istanbul Airport', 'city' => 'Arnavutkoy', 'state' => 'Istanbul', 'country' => 'TR', 'lat' => 41.262222, 'lon' => 28.727778, 'elevation' => 325, 'tz' => 'Europe/Istanbul'],
            'LTFJ' => ['icao' => 'LTFJ', 'iata' => 'SAW', 'name' => 'Sabiha Gokcen International Airport', 'city' => 'Istanbul', 'state' => 'Istanbul', 'country' => 'TR', 'lat' => 40.898602, 'lon' => 29.3092, 'elevation' => 312, 'tz' => 'Europe/Istanbul'],
            'LTBA' => ['icao' => 'LTBA', 'iata' => 'ISL', 'name' => 'Ataturk International Airport', 'city' => 'Istanbul', 'state' => 'Istanbul', 'country' => 'TR', 'lat' => 40.976898, 'lon' => 28.8146],
            'LTBW' => ['icao' => 'LTBW', 'iata' => '', 'name' => 'Istanbul Hezarfen Airfield', 'city' => 'Istanbul', 'state' => 'Istanbul', 'country' => 'TR', 'lat' => 41.1036, 'lon' => 28.5477],
        ])]);

        $this->artisan('airports:import')->assertSuccessful();

        $this->assertDatabaseCount('airports', 3);
        $this->assertDatabaseMissing('airports', ['icao_code' => 'LTBW']);
        $this->assertSame(['IST', 'SAW'], collect(app(AirportLookup::class)->forCity('TR', 'Istanbul'))->pluck('iataCode')->sort()->values()->all());
        $this->assertTrue(Airport::query()->whereNotNull('iata_code')->where('iata_code', '<>', '')->count() === Airport::count());
    }
}
