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
        self::invitingMember($request);
    }

    public static function invitingMember(Request $request): User
    {
        try {
            $grant = json_decode(Crypt::decryptString((string) $request->header('X-Kroo-Invitation')), true);
        } catch (DecryptException $exception) {
            abort(403, 'A valid member referral is required to join Kroo.');
        }
        $member = is_array($grant) && isset($grant['invitedBy'])
            ? User::whereKey($grant['invitedBy'])->first()
            : null;
        abort_unless($member, 403, 'A valid member referral is required to join Kroo.');

        return $member;
    }
}
