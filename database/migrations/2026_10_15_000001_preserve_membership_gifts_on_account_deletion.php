<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_gifts', function (Blueprint $table): void {
            $table->dropForeign(['buyer_id']);
            $table->dropForeign(['redeemed_by']);
        });
        Schema::table('membership_gifts', function (Blueprint $table): void {
            $table->uuid('buyer_id')->nullable()->change();
            $table->foreign('buyer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('redeemed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Keep buyer_id nullable because deleted buyers cannot be restored.
        Schema::table('membership_gifts', function (Blueprint $table): void {
            $table->dropForeign(['buyer_id']);
            $table->dropForeign(['redeemed_by']);
            $table->foreign('buyer_id')->references('id')->on('users');
            $table->foreign('redeemed_by')->references('id')->on('users');
        });
    }
};
