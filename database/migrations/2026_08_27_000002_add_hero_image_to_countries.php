<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            $table->string('hero_image')->nullable()->after('flag');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            $table->dropColumn('hero_image');
        });
    }
};
