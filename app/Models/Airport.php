<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source_id', 'name', 'country_code', 'municipality', 'normalized_municipality', 'iata_code', 'icao_code', 'latitude', 'longitude'])]
class Airport extends Model
{
    protected $primaryKey = 'source_id';

    public $incrementing = false;
}
