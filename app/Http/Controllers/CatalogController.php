<?php

namespace App\Http\Controllers;

use App\Models\CatalogVersion;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function version(): JsonResponse
    {
        $item = CatalogVersion::where('dataset', 'world-cities.csv')->latest('imported_at')->firstOrFail();

        return response()->json(['version' => $item->version, 'source' => $item->dataset, 'cityCount' => $item->row_count, 'importedAt' => $item->imported_at->utc()->toISOString()]);
    }
}
