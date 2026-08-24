<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return ['code' => $this->code, 'name' => $this->name, 'continentCode' => $this->continent_code, 'flag' => $this->flag];
    }
}
