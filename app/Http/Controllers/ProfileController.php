<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profile($request));
    }

    public function update(ProfileRequest $request): JsonResponse
    {
        $data = $request->validated();
        foreach (['dateOfBirth' => 'date_of_birth', 'photoUri' => 'photo_uri'] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $data[$column] = $data[$input];
                unset($data[$input]);
            }
        }
        $request->user()->update($data);

        return response()->json($this->profile($request));
    }

    private function profile(Request $request): array
    {
        $user = $request->user()->refresh();

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'language' => $user->language, 'plan' => $user->plan, 'nationality' => $user->nationality, 'dateOfBirth' => $user->date_of_birth?->format('Y-m-d'), 'photoUri' => $user->photo_uri];
    }
}
