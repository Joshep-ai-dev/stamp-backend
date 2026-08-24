<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['dataset', 'version', 'checksum', 'row_count', 'imported_at'])]
class CatalogVersion extends Model
{
    protected function casts(): array
    {
        return ['imported_at' => 'immutable_datetime'];
    }
}
