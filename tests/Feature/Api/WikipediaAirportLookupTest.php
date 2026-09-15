<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikipediaAirportLookupTest extends TestCase
{
    public function test_city_airports_are_loaded_from_wikipedia_and_iata_results_come_first(): void
    {
        Http::fake([
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => [
                ['pageid' => 1, 'index' => 1, 'title' => 'Local Airport', 'extract' => 'A public airport without a commercial code.'],
                ['pageid' => 2, 'index' => 2, 'title' => 'Charles de Gaulle Airport', 'extract' => 'IATA: CDG, ICAO: LFPG, serves Paris.'],
                ['pageid' => 3, 'index' => 3, 'title' => 'Paris', 'extract' => 'The capital of France.'],
            ]]], 200),
        ]);

        $this->getJson('/api/v1/catalog/airports?city=Paris&country=France')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Charles de Gaulle Airport')
            ->assertJsonPath('0.iataCode', 'CDG')
            ->assertJsonPath('0.icaoCode', 'LFPG')
            ->assertJsonPath('1.name', 'Local Airport');

        Http::assertSent(fn ($request): bool => $request['gsrsearch'] === 'airport Paris France'
            && $request['generator'] === 'search');
    }

    public function test_wikipedia_failure_returns_an_empty_airport_list(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response([], 503)]);

        $this->getJson('/api/v1/catalog/airports?city=Pattaya&country=Thailand')
            ->assertOk()
            ->assertExactJson([]);
    }
}
