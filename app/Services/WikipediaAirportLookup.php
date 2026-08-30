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
            ->filter(fn (array $airport): bool => collect(preg_split('~\s*/\s*~', $airport['municipality']))
                ->contains(fn (string $municipality): bool => $this->normalize($municipality) === $city))
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

        preg_match_all('~<h4\b[^>]*>.*?</h4>|<table\b[^>]*>.*?</table>~is', $html, $blocks);
        $countryCode = null;
        $airports = [];

        foreach ($blocks[0] as $block) {
            if (preg_match('~^<h4\b~i', $block)) {
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

                if (count($cells) >= 3) {
                    [$municipality, $name, $iataCode] = array_slice($cells, 0, 3);
                    $lastMunicipality = $municipality;
                } elseif (count($cells) === 2 && $lastMunicipality) {
                    [$name, $iataCode] = $cells;
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
}
