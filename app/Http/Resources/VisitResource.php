<?php

namespace App\Http\Resources;

use App\Services\ImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'cityId' => $this->city->geoname_id, 'cityName' => $this->city_name, 'country' => $this->country, 'countryCode' => $this->country_code, 'continentCode' => $this->continent_code, 'subcountry' => $this->subcountry, 'image' => ImageUrl::public($this->city->image_url), 'visitedAt' => $this->visited_at->format('Y-m-d'), 'note' => $this->note, 'places' => $this->places ?? [], 'userId' => $this->user_id];
    }
}
