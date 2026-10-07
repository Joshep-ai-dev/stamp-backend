<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MembershipGift extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected $hidden = ['encrypted_code', 'code_hash', 'baseline_transactions'];
    protected function casts(): array
    {
        return [
            'baseline_transactions' => 'array', 'encrypted_code' => 'encrypted',
            'paid_at' => 'immutable_datetime', 'email_sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime', 'redeemed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
