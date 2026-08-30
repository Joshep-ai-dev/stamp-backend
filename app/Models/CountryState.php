<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['country_code', 'name', 'normalized_name', 'image_url'])]
class CountryState extends Model
{
}
