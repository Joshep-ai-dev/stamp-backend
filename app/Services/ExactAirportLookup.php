<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ExactAirportLookup
{
    public function resolveGeoNamesId(string $city, string $state, string $countryCode): ?string
    {
        $username = trim((string) config('services.geonames.username'));
        if ($username === '') {
            return null;
        }
        $cacheKey = 'geonames-city:'.hash('sha256', $this->normalize($city).'|'.$this->normalizeRegion($state).'|'.strtoupper($countryCode));
        $cached = Cache::get($cacheKey);
        if (is_string($cached)) {
            return $cached !== '' ? $cached : null;
        }

        try {
            $response = Http::acceptJson()->timeout(8)->retry(2, 200)
                ->get('https://secure.geonames.org/searchJSON', [
                    'name_equals' => $city,
                    'country' => strtoupper($countryCode),
                    'featureClass' => 'P',
                    'maxRows' => 20,
                    'style' => 'FULL',
                    'username' => $username,
                ])->throw();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $cityName = $this->normalize($city);
        $regionName = $this->normalizeRegion($state);

        foreach ($response->json('geonames', []) as $place) {
            $names = [$place['name'] ?? '', $place['toponymName'] ?? ''];
            if (strtoupper((string) ($place['countryCode'] ?? '')) === strtoupper($countryCode)
                && collect($names)->contains(fn ($name): bool => $this->normalize((string) $name) === $cityName)
                && ($regionName === '' || $this->normalizeRegion((string) ($place['adminName1'] ?? '')) === $regionName)) {
                $geonamesId = isset($place['geonameId']) ? (string) $place['geonameId'] : '';
                Cache::put($cacheKey, $geonamesId, now()->addDay());

                return $geonamesId !== '' ? $geonamesId : null;
            }
        }

        Cache::put($cacheKey, '', now()->addDay());

        return null;
    }

    public function forGeoNamesId(string $geonamesId): array
    {
        $geonamesId = preg_replace('/\D/', '', $geonamesId) ?? '';
        if ($geonamesId === '') {
            return [];
        }

        return Cache::remember("airport-relations:geonames:{$geonamesId}", now()->addDay(), function () use ($geonamesId): array {
            $query = <<<SPARQL
SELECT DISTINCT ?airport ?airportLabel ?iata ?icao WHERE {
  ?city wdt:P1566 "{$geonamesId}".
  ?airport (wdt:P931|wdt:P138) ?city;
           wdt:P238 ?iata.
  OPTIONAL { ?airport wdt:P239 ?icao. }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
SPARQL;

            try {
                $response = Http::accept('application/sparql-results+json')
                    ->withUserAgent(config('app.name').' airport lookup/1.0 ('.config('app.url').')')
                    ->timeout(12)->retry(2, 300)
                    ->get('https://query.wikidata.org/sparql', [
                        'query' => $query,
                        'format' => 'json',
                    ])->throw();
            } catch (ConnectionException|RequestException) {
                return [];
            }

            return collect($response->json('results.bindings', []))
                ->map(function (array $binding): ?array {
                    $entity = basename((string) data_get($binding, 'airport.value', ''));
                    $name = (string) data_get($binding, 'airportLabel.value', '');
                    $iata = strtoupper((string) data_get($binding, 'iata.value', ''));
                    if ($entity === '' || $name === '' || ! preg_match('/^[A-Z0-9]{3}$/', $iata)) {
                        return null;
                    }

                    $icao = strtoupper((string) data_get($binding, 'icao.value', ''));

                    return [
                        'id' => "wikidata:{$entity}",
                        'name' => $name,
                        'iataCode' => $iata,
                        'icaoCode' => $icao !== '' ? $icao : null,
                    ];
                })->filter()->unique('id')->values()->all();
        });
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }

    private function normalizeRegion(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', $this->normalize($value)) ?? '';
    }
}
