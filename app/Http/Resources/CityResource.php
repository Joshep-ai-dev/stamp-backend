<?php

namespace App\Http\Resources;

use App\Services\ImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return ['id' => $this->geoname_id, 'name' => $this->name, 'country' => $this->country->name, 'countryCode' => $this->country_code, 'continentCode' => $this->country->continent_code, 'subcountry' => $this->subcountry, 'latitude' => $this->latitude, 'longitude' => $this->longitude, 'population' => $this->population, 'image' => ImageUrl::public($this->image_url)];
    }
}
