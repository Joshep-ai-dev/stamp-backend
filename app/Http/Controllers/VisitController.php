<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VisitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return VisitResource::collection($request->user()->visits()->with('city')->latest('visited_at')->latest('created_at')->get());
    }

    public function store(VisitRequest $request): JsonResponse
    {
        $visit = $request->user()->visits()->create($this->authoritativeData($request));

        return (new VisitResource($visit->load('city')))->response()->setStatusCode(201);
    }

    public function update(VisitRequest $request, string $visit): VisitResource
    {
        $model = $request->user()->visits()->findOrFail($visit);
        $model->update($this->authoritativeData($request));

        return new VisitResource($model->load('city'));
    }

    public function destroy(Request $request, string $visit): Response
    {
        $request->user()->visits()->findOrFail($visit)->delete();

        return response()->noContent();
    }

    private function authoritativeData(VisitRequest $request): array
    {
        $city = City::with('country')->where('geoname_id', $request->string('cityId'))->firstOrFail();

        return ['city_id' => $city->id, 'city_name' => $city->name, 'country' => $city->country->name, 'country_code' => $city->country_code, 'continent_code' => $city->country->continent_code, 'subcountry' => $city->subcountry, 'visited_at' => $request->date('visitedAt')->format('Y-m-d'), 'note' => $request->input('note'), 'places' => $request->input('places', [])];
    }
}
