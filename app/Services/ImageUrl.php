<?php

namespace App\Services;

class ImageUrl
{
    public static function public(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (isset($parts['host'])) {
            if (! in_array(strtolower($parts['host']), ['localhost', '127.0.0.1'], true)) {
                return $url;
            }

            $url = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return url($url);
    }
}
