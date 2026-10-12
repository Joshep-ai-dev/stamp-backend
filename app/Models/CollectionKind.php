<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['id', 'image', 'explorer_image', 'hero_image', 'title', 'detail', 'country_code', 'access', 'is_published', 'display_order'])]
class CollectionKind extends Model
{
    protected $table = 'collectionkind';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'display_order' => 'integer'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function states(): BelongsToMany
    {
        return $this->belongsToMany(CountryState::class, 'collection_kind_states', 'collection_kind_id', 'country_state_id')
            ->withPivot('display_order')
            ->withTimestamps()
            ->orderBy('country_states.name');
    }

    public function sights(): BelongsToMany
    {
        return $this->belongsToMany(Sight::class, 'collection_kind_sights', 'collection_kind_id', 'sight_id')
            ->withPivot('display_order')
            ->withTimestamps()
            ->orderBy('sights.name');
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
