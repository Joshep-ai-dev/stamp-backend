<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WikipediaAirportLookup
{
    private const SOURCE = 'https://en.wikipedia.org/wiki/List_of_international_airports_by_country';

    public function __construct(private readonly CountryResolver $countryResolver) {}

    public function forCity(string $countryCode, string $cityName): array
    {
        $catalog = $this->download();
        $city = $this->normalize($cityName);

        return collect($catalog)
            ->where('countryCode', strtoupper($countryCode))
            ->filter(fn (array $airport): bool => collect($this->municipalityNames($airport['municipality']))
                ->contains(fn (string $municipality): bool => $this->cityNamesMatch($city, $municipality)))
            ->sortBy('name')
            ->values()
            ->map(fn (array $airport): array => [
                'id' => $airport['iataCode'],
                'name' => $airport['name'],
                'iataCode' => $airport['iataCode'],
                'icaoCode' => null,
            ])->all();
    }

    private function download(): array
    {
        $html = Http::withHeaders([
            'User-Agent' => 'Kroo Travel airport lookup/1.0 (Laravel application)',
            'Accept-Language' => 'en',
        ])->timeout(30)->get(self::SOURCE)->throw()->body();

        preg_match_all('~<h[34]\b[^>]*>.*?</h[34]>|<table\b[^>]*>.*?</table>~is', $html, $blocks);
        $countryCode = null;
        $airports = [];

        foreach ($blocks[0] as $block) {
            if (preg_match('~^<h[34]\b~i', $block)) {
                try {
                    $countryCode = $this->countryResolver->resolve($this->cleanHtml($block))['code'];
                } catch (Throwable) {
                    $countryCode = null;
                }

                continue;
            }

            $tableText = $this->cleanHtml($block);
            if (! $countryCode || ! str_contains($tableText, 'Location') || ! str_contains($tableText, 'IATA Code')) {
                continue;
            }

            preg_match_all('~<tr\b[^>]*>(.*?)</tr>~is', $block, $tableRows);
            $lastMunicipality = null;
            foreach ($tableRows[1] as $tableRow) {
                preg_match_all('~<td\b[^>]*>(.*?)</td>~is', $tableRow, $cells);
                $cells = array_map(fn (string $cell): string => $this->cleanHtml($cell), $cells[1]);

                $iataIndex = collect($cells)->search(fn (string $cell): bool => (bool) preg_match('/^[A-Z0-9]{3}$/', strtoupper(trim($cell))));
                if (is_int($iataIndex) && $iataIndex >= 2) {
                    $municipality = $cells[0];
                    $name = $cells[1];
                    $iataCode = $cells[$iataIndex];
                    $lastMunicipality = $municipality;
                } elseif ($iataIndex === 1 && $lastMunicipality) {
                    $name = $cells[0];
                    $iataCode = $cells[1];
                    $municipality = $lastMunicipality;
                } else {
                    continue;
                }

                $iataCode = strtoupper(trim($iataCode));
                if ($municipality !== '' && $name !== '' && preg_match('/^[A-Z0-9]{3}$/', $iataCode)) {
                    $airports[$iataCode] = compact('countryCode', 'municipality', 'name', 'iataCode');
                }
            }
        }

        if (count($airports) < 100) {
            throw new \RuntimeException('Wikipedia returned an invalid international-airports list.');
        }

        return array_values($airports);
    }

    private function cleanHtml(string $value): string
    {
        $value = preg_replace('~<(sup|style|script)\b[^>]*>.*?</\1>~is', '', $value) ?? $value;
        $value = preg_replace('~<br\s*/?>~i', ' ', $value) ?? $value;
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\[\s*(?:edit|\d+)\s*\]/i', '', $value) ?? $value;

        return Str::of($value)->squish()->toString();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }

    private function municipalityNames(string $value): array
    {
        return collect(preg_split('~\s*/\s*~', $value) ?: [])
            ->flatMap(fn (string $name): array => [$name, preg_replace('/\s*\([^)]*\)\s*/', ' ', $name) ?? $name])
            ->map(fn (string $name): string => $this->normalize($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function cityNamesMatch(string $city, string $municipality): bool
    {
        return $city === $municipality
            || str_starts_with($city, $municipality.' ')
            || str_starts_with($municipality, $city.' ');
    }
}
