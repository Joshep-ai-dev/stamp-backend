<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['geoname_id', 'name', 'ascii_name', 'normalized_name', 'country_code', 'iso3', 'subcountry', 'normalized_subcountry', 'latitude', 'longitude', 'population', 'capital', 'image_url', 'description'])]
class City extends Model
{
    public function englishName(): string
    {
        return filled($this->ascii_name)
            ? $this->ascii_name
            : (Str::ascii($this->name) ?: $this->name);
    }

    public function englishSubcountry(): ?string
    {
        if (! filled($this->subcountry)) {
            return $this->subcountry;
        }

        return Str::ascii($this->subcountry) ?: $this->subcountry;
    }

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'population' => 'integer'];
    }

    public function sights(): HasMany
    {
        return $this->hasMany(Sight::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }
}
