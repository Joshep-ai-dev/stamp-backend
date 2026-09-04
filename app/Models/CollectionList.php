<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['id', 'collectionkind_id', 'image', 'title', 'city_id', 'location', 'detail', 'access', 'display_order'])]
class CollectionList extends Model
{
    protected $table = 'collectionlist';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::created(function (self $item): void {
            // Preserve compatibility for imports and tests that still create
            // an item with the legacy primary collection column.
            $item->kinds()->syncWithoutDetaching([$item->collectionkind_id]);
        });
    }

    public function kind(): BelongsTo
    {
        return $this->belongsTo(CollectionKind::class, 'collectionkind_id');
    }

    public function kinds(): BelongsToMany
    {
        return $this->belongsToMany(
            CollectionKind::class,
            'collection_kind_lists',
            'collection_list_id',
            'collection_kind_id',
        )->withTimestamps()->orderBy('title');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
