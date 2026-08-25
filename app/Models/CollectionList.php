<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'collectionkind_id', 'image', 'title', 'city_id', 'location', 'detail', 'display_order'])]
class CollectionList extends Model
{
    protected $table = 'collectionlist';

    public $incrementing = false;

    protected $keyType = 'string';

    public function kind(): BelongsTo
    {
        return $this->belongsTo(CollectionKind::class, 'collectionkind_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
