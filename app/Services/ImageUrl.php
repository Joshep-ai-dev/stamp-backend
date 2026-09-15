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
            $host = strtolower($parts['host']);
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $requestHost = app()->bound('request') ? strtolower((string) request()->getHost()) : '';
            $localHosts = array_filter(['localhost', '127.0.0.1', $appHost, $requestHost]);

            if (! in_array($host, $localHosts, true)) {
                return $url;
            }

            $url = ($parts['path'] ?? '').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $file = public_path(ltrim($path, '/'));

        if ($path !== '' && is_file($file)) {
            parse_str($query, $parameters);
            $parameters['v'] = filemtime($file);
            $query = http_build_query($parameters);
        }

        return url($path).($query !== '' ? '?'.$query : '');
    }
}
