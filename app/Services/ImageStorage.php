<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ImageStorage
{
    public function store(UploadedFile $image, string $folder = ''): string
    {
        $relative = trim($folder, '/');
        $directory = public_path('images'.($relative ? '/'.$relative : ''));
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = Str::uuid().'.'.$image->guessExtension();
        $image->move($directory, $filename);

        return '/images'.($relative ? '/'.$relative : '').'/'.$filename;
    }

    public function delete(?string $url): void
    {
        $path = rawurldecode((string) (parse_url((string) $url, PHP_URL_PATH) ?? ''));
        if (! str_starts_with($path, '/images/') || str_contains($path, '..')) {
            return;
        }
        $file = public_path(ltrim($path, '/'));
        if (is_file($file)) {
            unlink($file);
        }
    }
}
