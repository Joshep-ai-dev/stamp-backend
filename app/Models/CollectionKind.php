<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['id', 'image', 'explorer_image', 'hero_image', 'title', 'detail', 'is_published', 'display_order'])]
class CollectionKind extends Model
{
    protected $table = 'collectionkind';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'display_order' => 'integer'];
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(
            CollectionList::class,
            'collection_kind_lists',
            'collection_kind_id',
            'collection_list_id',
        )->withTimestamps()->orderBy('title');
    }
}
