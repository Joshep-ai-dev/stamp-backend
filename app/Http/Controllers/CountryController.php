<?php

namespace App\Http\Controllers;

use App\Http\Resources\CountryResource;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['continent' => ['nullable', Rule::in(['AF', 'AN', 'AS', 'EU', 'NA', 'OC', 'SA'])]]);

        return CountryResource::collection(Country::query()->when($data['continent'] ?? null, fn ($q, $v) => $q->where('continent_code', $v))->orderBy('name')->get());
    }
}
