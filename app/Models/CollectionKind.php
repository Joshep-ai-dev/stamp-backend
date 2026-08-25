<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'image', 'title', 'detail', 'access', 'is_published', 'display_order'])]
class CollectionKind extends Model
{
    protected $table = 'collectionkind';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'display_order' => 'integer'];
    }

    public function lists(): HasMany
    {
        return $this->hasMany(CollectionList::class, 'collectionkind_id')->orderBy('title');
    }
}
