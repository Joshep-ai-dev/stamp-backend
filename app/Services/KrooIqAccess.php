<?php

namespace App\Services;

use App\Models\User;

final class KrooIqAccess
{
    public function canUse(User $user): bool
    {
        return ! config('features.kroo_iq_requires_kroo_plus', true)
            || $user->plan === 'pro';
    }
}
