<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'name', 'country', 'country_code', 'city', 'city_id', 'image_url', 'icon', 'content', 'question', 'options', 'correct_answer', 'publish_date', 'is_published', 'is_premium', 'display_order'])]
class DailyDestination extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['options' => 'array', 'correct_answer' => 'integer', 'publish_date' => 'date:Y-m-d', 'is_published' => 'boolean', 'is_premium' => 'boolean', 'display_order' => 'integer'];
    }
}
