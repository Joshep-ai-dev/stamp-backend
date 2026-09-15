<?php

namespace App\Http\Controllers;

use App\Models\Friend;
use App\Models\User;
use App\Services\InvitationAccess;
use App\Services\KrooId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:200']]);
        $code = trim($data['code']);
        $code = preg_replace('#^stampo://friend/#', '', $code);
        $member = $this->memberFor($code);
        abort_unless($member, 422, 'This referral code is not valid. Ask a Kroo member for their code.');

        return response()->json(['accessToken' => Crypt::encryptString(json_encode([
            'invitedBy' => $member->id, 'issuedAt' => now()->toIso8601String(),
        ]))]);
    }

    public function join(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:200'],
            'name' => ['required', 'string', 'min:1', 'max:80'],
        ]);
        $member = $this->memberFor(trim($data['code']));
        abort_unless($member, 422, 'This referral code is not valid. Ask a Kroo member for their code.');

        return $this->createMembership($member, trim($data['name']));
    }

    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'min:1', 'max:80']]);

        return $this->createMembership(InvitationAccess::invitingMember($request), trim($data['name']));
    }

    public function resume(Request $request): JsonResponse
    {
        $data = $request->validate(['krooId' => ['required', 'string', 'max:20']]);
        $krooId = KrooId::parse($data['krooId']);
        abort_unless($krooId, 422, 'This Kroo ID is not valid.');

        $user = User::where('kroo_id', $krooId)->first();
        abort_unless($user, 404, 'No Kroo member was found for this Kroo ID.');

        return response()->json($this->session($user));
    }

    private function createMembership(User $member, string $name): JsonResponse
    {
        $user = User::create([
            'name' => $name,
            'email' => null,
            'password' => Str::random(64),
            'email_opt_in' => true,
            'referred_by_user_id' => $member->id,
        ]);
        Friend::firstOrCreate([
            'user_id' => min($member->id, $user->id),
            'friend_id' => max($member->id, $user->id),
        ]);

        return response()->json([
            ...$this->session($user),
            'accessToken' => $this->accessToken($member),
        ], 201);
    }

    private function session(User $user): array
    {
        return [
            'token' => $user->createToken('Kroo mobile app')->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email ?? '',
                'language' => $user->language,
                'plan' => $user->plan,
                'emailOptIn' => $user->email_opt_in,
                'krooId' => $user->kroo_id,
                'formattedKrooId' => KrooId::format($user->kroo_id),
            ],
        ];
    }

    private function memberFor(string $code): ?User
    {
        $code = preg_replace('#^stampo://friend/#', '', trim($code));
        $krooId = KrooId::parse($code);

        return User::where('friend_code', $code)
            ->when($krooId, fn ($query) => $query->orWhere('kroo_id', $krooId))
            ->first();
    }

    private function accessToken(User $member): string
    {
        return Crypt::encryptString(json_encode([
            'invitedBy' => $member->id,
            'issuedAt' => now()->toIso8601String(),
        ]));
    }
}
