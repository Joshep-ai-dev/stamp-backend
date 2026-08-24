<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['cityId' => ['required', 'string', 'max:32', 'exists:cities,geoname_id'], 'cityName' => ['required', 'string', 'max:150'], 'country' => ['required', 'string', 'max:100'], 'countryCode' => ['required', 'string', 'size:2'], 'continentCode' => ['required', 'string', 'in:AF,AN,AS,EU,NA,OC,SA'], 'subcountry' => ['nullable', 'string', 'max:150'], 'visitedAt' => ['required', 'date_format:Y-m-d'], 'note' => ['nullable', 'string', 'max:140'], 'places' => ['sometimes', 'array'], 'places.*.id' => ['required', 'string', 'max:255'], 'places.*.name' => ['required', 'string', 'max:255'], 'places.*.type' => ['required', 'in:sight,airport']];
    }
}
