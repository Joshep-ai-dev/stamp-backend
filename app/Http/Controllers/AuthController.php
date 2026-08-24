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
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
