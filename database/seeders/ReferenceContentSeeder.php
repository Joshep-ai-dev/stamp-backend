<?php

namespace Database\Seeders;

use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Models\DailyDestination;
use App\Services\CatalogLocationResolver;
use Illuminate\Database\Seeder;

class ReferenceContentSeeder extends Seeder
{
    public function run(CatalogLocationResolver $locations): void
    {
        $file = database_path('data/reference-content.json');
        if (! is_file($file)) {
            return;
        }
        $data = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        foreach ($data['managedCollections'] ?? [] as $item) {
            $kind = CollectionKind::updateOrCreate(['id' => $item['id']], [
                'title' => $item['title'], 'detail' => $item['detail'] ?? $item['description'] ?? '',
                'image' => $item['imageUrl'] ?? '', 'access' => ($item['isPremium'] ?? false) ? 'pro' : 'free', 'is_published' => $item['isPublished'] ?? true,
                'display_order' => $item['displayOrder'] ?? 0,
            ]);
            foreach ($item['places'] ?? [] as $order => $place) {
                $city = $locations->find((string) ($place['country'] ?? ''), (string) ($place['city'] ?? ''));
                CollectionList::updateOrCreate(['id' => $place['id']], ['collectionkind_id' => $kind->id, 'image' => $place['imageUrl'] ?? '', 'title' => $place['name'] ?? $place['title'], 'city_id' => $city?->id, 'location' => collect([$city?->name ?? ($place['city'] ?? null), $city?->country?->name ?? ($place['country'] ?? null)])->filter()->join(', '), 'detail' => $place['content'] ?? $place['detail'] ?? '', 'display_order' => $order]);
            }
        }
        foreach ($data['dailyDestinations'] ?? [] as $item) {
            $city = $locations->find($item['country'], $item['city'] ?? '');
            DailyDestination::updateOrCreate(['id' => $item['id']], [
                'name' => $item['name'], 'country' => $city?->country?->name ?? $item['country'], 'country_code' => $city?->country_code,
                'city' => $city?->name ?? ($item['city'] ?? ''), 'city_id' => $city?->id,
                'image_url' => $item['imageUrl'] ?? '', 'icon' => $item['icon'] ?? '🌍', 'content' => $item['content'],
                'question' => $item['question'], 'options' => $item['options'], 'correct_answer' => $item['correctAnswer'],
                'publish_date' => ($item['publishDate'] ?? '') ?: null, 'is_published' => $item['isPublished'] ?? true,
                'is_premium' => $item['isPremium'] ?? false, 'display_order' => $item['displayOrder'] ?? 0,
            ]);
        }
    }
}
