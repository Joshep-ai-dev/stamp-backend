<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'cityId' => $this->city->geoname_id, 'cityName' => $this->city_name, 'country' => $this->country, 'countryCode' => $this->country_code, 'continentCode' => $this->continent_code, 'subcountry' => $this->subcountry, 'visitedAt' => $this->visited_at->format('Y-m-d'), 'note' => $this->note, 'places' => $this->places ?? [], 'userId' => $this->user_id];
    }
}
