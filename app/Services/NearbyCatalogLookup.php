<?php

namespace App\Services;

use App\Models\CollectionList;
use App\Models\Sight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NearbyCatalogLookup
{
    public function find(float $latitude, float $longitude, int $radius = 1000): array
    {
        $response = Http::withHeaders(['User-Agent' => 'Kroo Travel nearby-place lookup/1.0'])
            ->timeout(20)->get('https://en.wikipedia.org/w/api.php', [
                'action' => 'query', 'list' => 'geosearch',
                'gscoord' => $latitude.'|'.$longitude,
                'gsradius' => $radius, 'gslimit' => 50, 'gsnamespace' => 0,
                'format' => 'json', 'formatversion' => 2, 'origin' => '*',
            ])->throw();

        $nearby = collect($response->json('query.geosearch', []));
        $matches = [];
        foreach (Sight::with('city')->where('is_featured', true)->get() as $sight) {
            if ($page = $this->matchingPage($nearby, $sight->name)) {
                $matches[] = $this->result('sight', (string) $sight->id, $sight->name, $sight->city?->name, $sight->country_code, $page);
            }
        }
        foreach (CollectionList::with(['kind', 'city'])->whereHas('kind', fn ($query) => $query->where('is_published', true))->get() as $place) {
            if ($page = $this->matchingPage($nearby, $place->title)) {
                $matches[] = $this->result('collection', (string) $place->id, $place->title, $place->city?->name, $place->city?->country_code, $page);
            }
        }

        usort($matches, fn (array $a, array $b) => $a['distanceMeters'] <=> $b['distanceMeters']);

        return $matches;
    }

    private function matchingPage($pages, string $name): ?array
    {
        $name = $this->normalize($name);
        if (mb_strlen($name) < 4) return null;

        return $pages->first(function (array $page) use ($name): bool {
            $title = $this->normalize((string) ($page['title'] ?? ''));
            return $title === $name || str_contains($title, $name) || str_contains($name, $title);
        });
    }

    private function result(string $type, string $id, string $name, ?string $city, ?string $countryCode, array $page): array
    {
        return ['id' => $id, 'type' => $type, 'name' => $name, 'city' => $city, 'countryCode' => $countryCode, 'distanceMeters' => (int) round((float) $page['dist']), 'latitude' => (float) $page['lat'], 'longitude' => (float) $page['lon'], 'wikipediaTitle' => $page['title']];
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
