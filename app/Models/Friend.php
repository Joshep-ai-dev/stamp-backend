<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'friend_id'])]
class Friend extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
