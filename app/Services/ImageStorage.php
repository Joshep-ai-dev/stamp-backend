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
        $this->optimize($directory.'/'.$filename);

        return '/images'.($relative ? '/'.$relative : '').'/'.$filename;
    }

    private function optimize(string $path): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }

        $details = @getimagesize($path);
        $contents = @file_get_contents($path);
        $source = $contents === false ? false : @imagecreatefromstring($contents);
        if (! $details || ! $source) {
            return;
        }

        [$width, $height] = $details;
        $mime = $details['mime'] ?? '';
        $maximum = $mime === 'image/png' ? 900 : 1200;
        $ratio = min(1, $maximum / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
        }
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $optimizedPath = $path.'.optimized';
        match ($mime) {
            'image/jpeg' => imagejpeg($target, $optimizedPath, 82),
            'image/png' => imagepng($target, $optimizedPath, 9),
            'image/webp' => imagewebp($target, $optimizedPath, 82),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($target);

        if (is_file($optimizedPath) && filesize($optimizedPath) < filesize($path)) {
            rename($optimizedPath, $path);
        } elseif (is_file($optimizedPath)) {
            unlink($optimizedPath);
        }
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
