<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'normalized_name', 'continent_code', 'flag', 'hero_image'])]
class Country extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'country_code', 'code');
    }
}
