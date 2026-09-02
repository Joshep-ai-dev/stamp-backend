<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'language' => $this->language,
            'plan' => $this->plan,
            'nationality' => $this->nationality,
            'dateOfBirth' => $this->date_of_birth?->format('Y-m-d'),
            'sex' => $this->sex,
        ];
    }
}
