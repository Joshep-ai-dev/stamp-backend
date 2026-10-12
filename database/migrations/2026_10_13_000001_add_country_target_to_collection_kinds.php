<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collectionkind', function (Blueprint $table): void {
            $table->char('country_code', 2)->nullable()->after('detail')->index();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('collectionkind', function (Blueprint $table): void {
            $table->dropForeign(['country_code']);
            $table->dropIndex(['country_code']);
            $table->dropColumn('country_code');
        });
    }
};
