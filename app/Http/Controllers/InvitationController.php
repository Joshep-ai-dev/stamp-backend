<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class InvitationController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:200']]);
        $code = trim($data['code']);
        $code = preg_replace('#^stampo://friend/#', '', $code);
        $member = User::where('friend_code', $code)->first();
        abort_unless($member, 422, 'This referral code is not valid. Ask a Kroo member for their code.');

        return response()->json(['accessToken' => Crypt::encryptString(json_encode([
            'invitedBy' => $member->id, 'issuedAt' => now()->toIso8601String(),
        ]))]);
    }
}
