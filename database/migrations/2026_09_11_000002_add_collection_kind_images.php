<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collectionkind', function (Blueprint $table): void {
            $table->text('explorer_image')->nullable()->after('image');
            $table->text('hero_image')->nullable()->after('explorer_image');
        });

        DB::table('collectionkind')->whereNull('hero_image')->update(['hero_image' => DB::raw('image')]);
    }

    public function down(): void
    {
        Schema::table('collectionkind', function (Blueprint $table): void {
            $table->dropColumn(['explorer_image', 'hero_image']);
        });
    }
};
