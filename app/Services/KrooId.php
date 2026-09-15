<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class KrooId
{
    public const MAXIMUM = 1_000_000_000;

    public function allocate(): int
    {
        $id = DB::table('kroo_id_allocations')->insertGetId([
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        throw_if($id > self::MAXIMUM, RuntimeException::class, 'Kroo ID capacity has been reached.');

        return $id;
    }

    public static function format(int $id): string
    {
        return 'KROO-'.str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    }
}
