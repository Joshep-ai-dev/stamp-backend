<?php

namespace Tests\Feature\Api;

use App\Models\Airport;
use App\Models\Country;
use App\Services\AirportLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAirportsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_iata_airports_and_resolves_istanbul_from_city_and_state(): void
    {
        Country::firstOrCreate(['code' => 'TR'], ['name' => 'Turkey', 'normalized_name' => 'turkey', 'continent_code' => 'AS', 'flag' => '']);
        $airports = tempnam(sys_get_temp_dir(), 'airports-');
        $regions = tempnam(sys_get_temp_dir(), 'regions-');
        file_put_contents($airports, implode("\n", [
            'id,ident,type,name,latitude_deg,longitude_deg,elevation_ft,continent,iso_country,iso_region,municipality,scheduled_service,gps_code,iata_code,local_code,home_link,wikipedia_link,keywords',
            '1,LTFM,large_airport,Istanbul Airport,41.262222,28.727778,325,AS,TR,TR-34,Arnavutkoy,yes,LTFM,IST,,,,',
            '2,LTFJ,large_airport,Sabiha Gokcen International Airport,40.898602,29.3092,312,AS,TR,TR-34,Istanbul,yes,LTFJ,SAW,,,,',
            '3,LTBA,large_airport,Ataturk International Airport,40.976898,28.8146,163,AS,TR,TR-34,Istanbul,no,LTBA,ISL,,,,',
            '4,LTBW,small_airport,Istanbul Hezarfen Airfield,41.1036,28.5477,56,AS,TR,TR-34,Istanbul,no,LTBW,,,,,',
        ]));
        file_put_contents($regions, "id,code,local_code,name,continent,iso_country,wikipedia_link,keywords\n1,TR-34,34,Istanbul,AS,TR,,");

        $this->artisan('airports:import', ['--source' => $airports, '--regions' => $regions])->assertSuccessful();
        unlink($airports);
        unlink($regions);

        $this->assertDatabaseCount('airports', 3);
        $this->assertDatabaseMissing('airports', ['icao_code' => 'LTBW']);
        $this->assertSame(['ISL', 'IST', 'SAW'], collect(app(AirportLookup::class)->forCity('TR', 'Istanbul'))->pluck('iataCode')->sort()->values()->all());
        $this->assertTrue(Airport::query()->whereNotNull('iata_code')->where('iata_code', '<>', '')->count() === Airport::count());
    }
}
