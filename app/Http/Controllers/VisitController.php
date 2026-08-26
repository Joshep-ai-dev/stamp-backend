<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitRequest;
use App\Http\Resources\VisitResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class VisitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return VisitResource::collection($request->user()->visits()->with('city')->latest('visited_at')->latest('created_at')->get());
    }

    public function store(VisitRequest $request): JsonResponse
    {
        [$data, $city] = $this->authoritativeData($request);
        if ($request->user()->visits()->where('city_id', $city->id)->exists()) {
            throw ValidationException::withMessages([
                'cityId' => ['You have already added this city to your visits.'],
            ]);
        }
        $visit = $request->user()->visits()->create($data);
        $visit->setRelation('city', $city);

        return (new VisitResource($visit))->response()->setStatusCode(201);
    }

    /** Merge device-local visits into the account without deleting cloud data. */
    public function sync(Request $request): AnonymousResourceCollection
    {
        $payload = $request->validate([
            'visits' => ['present', 'array', 'max:1000'],
            'visits.*.cityId' => ['required', 'string', 'max:32'],
            'visits.*.visitedAt' => ['required', 'date_format:Y-m-d'],
            'visits.*.note' => ['nullable', 'string', 'max:140'],
            'visits.*.places' => ['sometimes', 'array'],
        ]);

        DB::transaction(function () use ($request, $payload): void {
            foreach ($payload['visits'] as $item) {
                $city = City::with('country')->where('geoname_id', $item['cityId'])->first();
                if (! $city) {
                    continue;
                }
                $request->user()->visits()->updateOrCreate(
                    ['city_id' => $city->id],
                    [
                        'city_name' => $city->name,
                        'country' => $city->country->name,
                        'country_code' => $city->country_code,
                        'continent_code' => $city->country->continent_code,
                        'subcountry' => $city->subcountry,
                        'visited_at' => $item['visitedAt'],
                        'note' => $item['note'] ?? null,
                        'places' => $item['places'] ?? [],
                    ],
                );
            }
        });

        return VisitResource::collection(
            $request->user()->visits()->with('city')->latest('visited_at')->latest('created_at')->get(),
        );
    }

    public function update(VisitRequest $request, string $visit): VisitResource
    {
        $model = $request->user()->visits()->findOrFail($visit);
        [$data, $city] = $this->authoritativeData($request);
        $model->update($data);
        $model->setRelation('city', $city);

        return new VisitResource($model);
    }

    public function destroy(Request $request, string $visit): Response
    {
        $request->user()->visits()->findOrFail($visit)->delete();

        return response()->noContent();
    }

    private function authoritativeData(VisitRequest $request): array
    {
        $city = City::with('country')->where('geoname_id', $request->string('cityId'))->firstOrFail();

        return [[
            'city_id' => $city->id, 'city_name' => $city->name, 'country' => $city->country->name,
            'country_code' => $city->country_code, 'continent_code' => $city->country->continent_code,
            'subcountry' => $city->subcountry, 'visited_at' => $request->date('visitedAt')->format('Y-m-d'),
            'note' => $request->input('note'), 'places' => $request->input('places', []),
        ], $city];
    }
}
