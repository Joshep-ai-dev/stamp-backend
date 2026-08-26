<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropUnique('visits_user_city_unique');
            $table->string('source_id', 191)->nullable()->after('user_id');
            $table->unique(['user_id', 'source_id'], 'visits_user_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropUnique('visits_user_source_unique');
            $table->dropColumn('source_id');
            $table->unique(['user_id', 'city_id'], 'visits_user_city_unique');
        });
    }
};
