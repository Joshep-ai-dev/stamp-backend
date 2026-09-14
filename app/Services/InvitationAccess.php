<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class InvitationAccess
{
    public static function requireInvitation(Request $request): void
    {
        try {
            $grant = json_decode(Crypt::decryptString((string) $request->header('X-Kroo-Invitation')), true);
        } catch (DecryptException $exception) {
            abort(403, 'A valid member referral is required to join Kroo.');
        }
        abort_unless(is_array($grant) && isset($grant['invitedBy'])
            && User::whereKey($grant['invitedBy'])->exists(), 403, 'A valid member referral is required to join Kroo.');
    }
}
