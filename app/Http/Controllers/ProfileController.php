<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Services\ImageStorage;
use App\Services\KrooId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->profile($request));
    }

    public function update(ProfileRequest $request, ImageStorage $images): JsonResponse
    {
        $data = $request->validated();
        if (array_key_exists('email', $data) && blank($data['email'])) {
            unset($data['email']);
        }
        $oldPhoto = $request->user()->photo_uri;
        foreach ([
            'familyName' => 'family_name',
            'phoneNumber' => 'phone_number',
            'dateOfBirth' => 'date_of_birth',
            'stateProvince' => 'state_province',
            'postalCode' => 'postal_code',
            'emailOptIn' => 'email_opt_in',
            'photoUri' => 'photo_uri',
        ] as $input => $column) {
            if (array_key_exists($input, $data)) {
                $data[$column] = $data[$input];
                unset($data[$input]);
            }
        }
        $request->user()->update($data);
        if (array_key_exists('photo_uri', $data) && $oldPhoto !== $data['photo_uri']) {
            $images->delete($oldPhoto);
        }

        return response()->json($this->profile($request));
    }

    public function uploadImage(Request $request, ImageStorage $images): JsonResponse
    {
        $data = $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240']]);
        $oldPhoto = $request->user()->photo_uri;
        $photoUri = $images->store($data['image'], 'users');
        $request->user()->update(['photo_uri' => $photoUri]);
        $images->delete($oldPhoto);

        return response()->json(['photoUri' => $photoUri], 201);
    }

    private function profile(Request $request): array
    {
        $user = $request->user()->refresh();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'familyName' => $user->family_name,
            'email' => str_ends_with($user->email, '@members.kroo.invalid') ? '' : $user->email,
            'krooId' => $user->kroo_id,
            'formattedKrooId' => KrooId::format($user->kroo_id),
            'phoneNumber' => $user->phone_number,
            'language' => $user->language,
            'emailOptIn' => $user->email_opt_in,
            'plan' => $user->plan,
            'nationality' => $user->nationality,
            'dateOfBirth' => $user->date_of_birth?->format('Y-m-d'),
            'address' => $user->address,
            'city' => $user->city,
            'stateProvince' => $user->state_province,
            'postalCode' => $user->postal_code,
            'country' => $user->country,
            'photoUri' => $user->photo_uri,
        ];
    }
}
