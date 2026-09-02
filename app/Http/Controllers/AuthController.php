<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'purpose' => ['required', Rule::in(['sign-in', 'create-account'])],
        ]);
        $email = strtolower(trim($data['email']));
        $accountExists = User::where('email', $email)->exists();
        $eligible = $data['purpose'] === 'sign-in' ? $accountExists : ! $accountExists;
        if ($eligible) {
            $code = (string) random_int(100000, 999999);
            Cache::put($this->codeKey($email, $data['purpose']), [
                'hash' => Hash::make($code),
                'attempts' => 0,
            ], now()->addMinutes(10));
            Mail::raw("Your Kroo verification code is {$code}. It expires in 10 minutes.", function ($message) use ($email): void {
                $message->to($email)->subject('Your Kroo verification code');
            });
        }

        return response()->json(['message' => 'If this email can be used, a verification code has been sent.']);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'code' => ['required', 'digits:6'],
            'purpose' => ['required', Rule::in(['sign-in', 'create-account'])],
            'name' => ['nullable', 'string', 'min:1', 'max:80'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'dateOfBirth' => ['nullable', 'date_format:Y-m-d'],
            'sex' => ['nullable', Rule::in(['M', 'F'])],
        ]);
        $email = strtolower(trim($data['email']));
        $key = $this->codeKey($email, $data['purpose']);
        $record = Cache::get($key);
        if (! $record || ($record['attempts'] ?? 0) >= 5 || ! Hash::check($data['code'], $record['hash'])) {
            if ($record) {
                $record['attempts'] = ($record['attempts'] ?? 0) + 1;
                Cache::put($key, $record, now()->addMinutes(10));
            }
            throw ValidationException::withMessages(['code' => ['The verification code is invalid or expired.']]);
        }
        Cache::forget($key);
        if ($data['purpose'] === 'sign-in') {
            $user = User::where('email', $email)->firstOrFail();
        } else {
            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => ['An account already exists for this email. Please sign in instead.']]);
            }
            $user = User::create([
                'email' => $email,
                'name' => trim($data['name'] ?? '') ?: Str::before($email, '@'),
                'password' => Str::random(64),
            ]);
            $user->update(collect($data)->only(['name', 'nationality', 'sex'])->filter(fn ($value) => filled($value))->all() + ['date_of_birth' => $data['dateOfBirth'] ?? null]);
        }
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json(['token' => $user->createToken('Kroo mobile app')->plainTextToken, 'user' => new UserResource($user)]);
    }

    private function codeKey(string $email, string $purpose): string
    {
        return 'auth-code:'.hash('sha256', $email).':'.$purpose;
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only('name', 'email', 'password'));

        return response()->json(['token' => $user->createToken($request->string('deviceName', 'mobile')->toString())->plainTextToken, 'user' => new UserResource($user)], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        return response()->json(['token' => $user->createToken($request->string('deviceName', 'mobile')->toString())->plainTextToken, 'user' => new UserResource($user)]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function password(Request $request): Response
    {
        $data = $request->validate(['currentPassword' => ['required', 'string'], 'newPassword' => ['required', 'string', 'min:8']]);
        if (! Hash::check($data['currentPassword'], $request->user()->password)) {
            throw ValidationException::withMessages(['currentPassword' => ['The current password is incorrect.']]);
        }
        $request->user()->update(['password' => $data['newPassword']]);

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
