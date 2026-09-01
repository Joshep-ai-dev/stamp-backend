<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_id', 'icao_code', 'iata_code', 'name', 'normalized_city', 'city', 'normalized_state', 'state', 'country_code', 'latitude', 'longitude', 'elevation', 'timezone'])]
class Airport extends Model
{
    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'elevation' => 'integer'];
    }
}
