<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('countries')->whereNotNull('hero_image')->where('hero_image', '<>', '')
            ->chunkById(100, function ($countries): void {
                foreach ($countries as $country) {
                    $host = strtolower((string) parse_url($country->hero_image, PHP_URL_HOST));
                    $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
                    if ($host !== '' && ! in_array($host, array_filter(['localhost', '127.0.0.1', $appHost]), true)) {
                        continue;
                    }

                    $path = rawurldecode((string) parse_url($country->hero_image, PHP_URL_PATH));
                    if (! str_starts_with($path, '/images/countries/') || str_contains($path, '..')) {
                        continue;
                    }

                    $file = public_path(ltrim($path, '/'));
                    if (! is_file($file) || @getimagesize($file) === false) {
                        DB::table('countries')->where('code', $country->code)->update(['hero_image' => '']);
                    }
                }
            }, 'code');
    }

    public function down(): void
    {
        // Removed paths cannot be restored reliably.
    }
};
