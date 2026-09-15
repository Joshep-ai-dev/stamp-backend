<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WikipediaAirportLookup
{
    public function forCity(string $cityName, string $country, ?string $countryCode = null): array
    {
        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('app.name').' airport lookup/1.0 ('.config('app.url').')')
                ->timeout(8)
                ->retry(2, 200)
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action' => 'query',
                    'generator' => 'search',
                    'gsrsearch' => "airport {$cityName} {$country}",
                    'gsrlimit' => 10,
                    'prop' => 'extracts',
                    'exintro' => 1,
                    'explaintext' => 1,
                    'format' => 'json',
                    'formatversion' => 2,
                ])->throw();
        } catch (ConnectionException|RequestException) {
            return [];
        }

        return collect($response->json('query.pages', []))
            ->filter(function (array $page): bool {
                $title = strtolower($page['title'] ?? '');

                return str_contains($title, 'airport') && ! str_starts_with($title, 'list of ');
            })
            ->sortBy('index')
            ->map(function (array $page) use ($cityName, $country, $countryCode): array {
                $text = ($page['title'] ?? '').' '.($page['extract'] ?? '');
                preg_match('/\bIATA(?: airport code)?\s*:\s*([A-Z0-9]{3})\b/i', $text, $iata);
                preg_match('/\bICAO(?: airport code)?\s*:\s*([A-Z0-9]{4})\b/i', $text, $icao);

                return [
                    'id' => 'wikipedia:'.($page['pageid'] ?? md5($page['title'] ?? $text)),
                    'name' => $page['title'] ?? 'Airport',
                    'iataCode' => strtoupper($iata[1] ?? ''),
                    'icaoCode' => isset($icao[1]) ? strtoupper($icao[1]) : null,
                    'city' => $cityName,
                    'countryCode' => strtoupper($countryCode ?? $country),
                ];
            })
            ->sortBy(fn (array $airport): int => $airport['iataCode'] === '' ? 1 : 0)
            ->values()
            ->all();
    }
}
