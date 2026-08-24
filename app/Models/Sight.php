<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['country_code', 'city_id', 'name', 'slug', 'description', 'image_url', 'is_featured', 'is_premium', 'display_order'])]
class Sight extends Model
{
    protected function casts(): array
    {
        return ['is_featured' => 'boolean', 'is_premium' => 'boolean', 'display_order' => 'integer'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
