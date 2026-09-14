<?php

namespace App\Services;

use App\Models\CollectionList;
use App\Models\User;

class CollectionAccess
{
    public static function checkCompletion(User $user, string $target): void
    {
        if ($user->plan === 'pro') {
            return;
        }
        $items = CollectionList::with('kinds')->get();
        foreach ($items as $item) {
            foreach ($item->kinds as $kind) {
                if ("collection-{$kind->id}-{$item->id}" === $target) {
                    abort_if($kind->access === 'pro' || $item->access === 'pro', 403, 'Kroo+ membership is required.');

                    return;
                }
            }
        }
        $linked = $items->filter(fn ($item) => $item->sight_id !== null && (string) $item->sight_id === $target);
        if ($linked->isNotEmpty()) {
            $hasFreeAccess = $linked->contains(fn ($item) => $item->access !== 'pro' && $item->kinds->contains(fn ($kind) => $kind->is_published && $kind->access !== 'pro'));
            abort_unless($hasFreeAccess, 403, 'Kroo+ membership is required.');
        }
    }
}
