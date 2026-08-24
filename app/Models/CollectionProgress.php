<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'collection_id', 'progress'])]
class CollectionProgress extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'collection_progress';

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['progress' => 'integer'];
    }
}
