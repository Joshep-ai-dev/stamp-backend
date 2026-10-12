<?php

namespace App\Services;

use App\Models\CollectionKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CollectionSourcePlaces
{
    public function lists(CollectionKind $kind): Collection
    {
        $kind->loadMissing(['lists.city.country', 'lists.sight.city.country']);
        $seenStates = [];
        $seenSights = [];
        $normalize = fn (?string $value): string => Str::of($value ?? '')->ascii()->lower()->squish()->toString();
        $normalizeState = fn (?string $value): string => UsStates::normalize($value);

        return $kind->lists->sortBy(fn ($list) => $list->sight?->name ?? $list->title ?? '')
            ->filter(function ($list) use (&$seenStates, &$seenSights, $normalize, $normalizeState): bool {
            $title = $list->sight?->name ?? $list->title;
            $city = $list->sight?->city ?? $list->city;
            if ($city?->country_code === 'US' && $city?->subcountry
                && $normalizeState($title) === $normalizeState($city->subcountry)) {
                $key = $normalizeState($title);
                if (isset($seenStates[$key])) {
                    return false;
                }
                $seenStates[$key] = true;

                return true;
            }

            $sightKey = $list->sight_id
                ? (string) $list->sight_id
                : ($list->city_id && $title ? $list->city_id.'|'.$normalize($title) : null);
            if ($sightKey !== null && isset($seenSights[$sightKey])) {
                return false;
            }
            if ($sightKey !== null) {
                $seenSights[$sightKey] = true;
            }

            return true;
        })->values();
    }

    public function extras(CollectionKind $kind, bool $hasAccess = false): Collection
    {
        $kind->loadMissing(['lists.city.country', 'lists.sight.city.country', 'states', 'sights.city.country']);
        $lists = $this->lists($kind);
        $normalize = fn (?string $value): string => Str::of($value ?? '')->ascii()->lower()->squish()->toString();
        $normalizeState = fn (?string $value): string => UsStates::normalize($value);

        $listedStates = $lists->filter(function ($list) use ($normalizeState): bool {
            $city = $list->sight?->city ?? $list->city;
            $title = $list->sight?->name ?? $list->title;

            return $city?->country_code === 'US'
                && $city?->subcountry
                && $normalizeState($title) === $normalizeState($city->subcountry);
        })->map(fn ($list) => $normalizeState($list->sight?->name ?? $list->title))->all();

        $statePlaces = $kind->states
            ->reject(fn ($state) => in_array($normalizeState($state->name), $listedStates, true))
            ->map(fn ($state) => [
                'id' => 'state-'.$state->id,
                'stateId' => (string) $state->id,
                'type' => 'state',
                'name' => $state->name,
                'title' => $state->name,
                'countryId' => 'US',
                'country' => 'United States',
                'imageUrl' => ImageUrl::public($state->image_url),
                'detail' => ($hasAccess || $kind->access !== 'pro') ? ($state->description ?? '') : '',
                'content' => ($hasAccess || $kind->access !== 'pro') ? ($state->description ?? '') : '',
                'access' => $kind->access ?? 'free',
                'isPremium' => $kind->access === 'pro',
            ]);

        $listedSightIds = $lists->pluck('sight_id')->filter()->map(fn ($id) => (string) $id)->all();
        $stateSightPlaces = $kind->sights
            ->reject(fn ($sight) => in_array((string) $sight->id, $listedSightIds, true)
                || $lists->contains(fn ($list) => ($list->sight?->id === $sight->id)
                    || (($list->sight?->city_id ?? $list->city_id) === $sight->city_id
                        && $normalize($list->sight?->name ?? $list->title) === $normalize($sight->name))))
            ->map(fn ($sight) => [
                'id' => (string) $sight->id,
                'sightId' => (string) $sight->id,
                'type' => 'sight',
                'name' => $sight->name,
                'title' => $sight->name,
                'cityId' => $sight->city?->geoname_id,
                'city' => $sight->city?->name,
                'state' => $sight->city?->subcountry,
                'countryId' => $sight->country_code,
                'country' => $sight->country?->name,
                'imageUrl' => ImageUrl::public($sight->image_url),
                'detail' => ($hasAccess || ($kind->access !== 'pro' && ! $sight->is_premium)) ? $sight->description : '',
                'content' => ($hasAccess || ($kind->access !== 'pro' && ! $sight->is_premium)) ? $sight->description : '',
                'access' => $kind->access === 'pro' || $sight->is_premium ? 'pro' : 'free',
                'isPremium' => $kind->access === 'pro' || $sight->is_premium,
            ]);

        return $statePlaces->concat($stateSightPlaces)->values();
    }
}
