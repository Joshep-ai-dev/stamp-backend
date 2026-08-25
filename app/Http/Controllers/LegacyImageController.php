<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LegacyImageController extends Controller
{
    public function public(string $folder, string $filename): BinaryFileResponse
    {
        abort_unless(in_array($folder, ['sights', 'users', 'collection', 'daily-destinations'], true), 404);
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);
        $file = public_path("images/{$folder}/{$filename}");
        abort_unless(is_file($file), 404);

        return response()->file($file, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function show(string $filename): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $filename), 404);
        $file = storage_path('app/public/images/'.$filename);
        abort_unless(is_file($file), 404);

        return response()->file($file);
    }
}
