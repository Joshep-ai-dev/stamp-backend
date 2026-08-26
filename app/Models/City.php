<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['geoname_id', 'name', 'ascii_name', 'normalized_name', 'country_code', 'iso3', 'subcountry', 'normalized_subcountry', 'latitude', 'longitude', 'population', 'capital'])]
class City extends Model
{
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'population' => 'integer'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
