<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->string('title')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('collectionlist')->whereNull('title')->orderBy('id')->each(function (object $item): void {
            $title = $item->sight_id
                ? DB::table('sights')->where('id', $item->sight_id)->value('name')
                : null;

            DB::table('collectionlist')->where('id', $item->id)->update(['title' => $title ?? '']);
        });

        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->string('title')->nullable(false)->change();
        });
    }
};
