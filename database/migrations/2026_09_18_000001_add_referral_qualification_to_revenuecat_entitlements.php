<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenuecat_entitlements', function (Blueprint $table): void {
            $table->timestampTz('paid_membership_started_at')->nullable()->index();
            $table->timestampTz('referral_qualified_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('revenuecat_entitlements', function (Blueprint $table): void {
            $table->dropColumn(['paid_membership_started_at', 'referral_qualified_at']);
        });
    }
};
