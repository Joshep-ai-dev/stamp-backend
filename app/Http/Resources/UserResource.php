<?php

namespace App\Http\Resources;

use App\Services\KrooId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'krooId' => $this->kroo_id,
            'formattedKrooId' => KrooId::format($this->kroo_id),
            'name' => $this->name,
            'familyName' => $this->family_name,
            'email' => $this->email ?? '',
            'phoneNumber' => $this->phone_number,
            'language' => $this->language,
            'emailOptIn' => $this->email_opt_in,
            'plan' => $this->plan,
            'nationality' => $this->nationality,
            'dateOfBirth' => $this->date_of_birth?->format('Y-m-d'),
            'address' => $this->address,
            'city' => $this->city,
            'stateProvince' => $this->state_province,
            'postalCode' => $this->postal_code,
            'country' => $this->country,
        ];
    }
}
