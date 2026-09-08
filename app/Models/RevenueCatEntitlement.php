<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'app_user_id',
    'entitlement_id',
    'product_id',
    'store',
    'period_type',
    'is_active',
    'expires_at',
    'last_event_id',
    'last_event_type',
    'last_verified_at',
    'subscriber_payload',
])]
class RevenueCatEntitlement extends Model
{
    use HasUuids;

    protected $table = 'revenuecat_entitlements';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'subscriber_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
