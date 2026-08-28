<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['country_code', 'name', 'normalized_name'])]
class CountryState extends Model
{
}
