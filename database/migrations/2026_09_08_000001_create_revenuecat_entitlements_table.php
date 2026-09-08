<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenuecat_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('app_user_id')->unique();
            $table->string('entitlement_id');
            $table->string('product_id')->nullable();
            $table->string('store')->nullable();
            $table->string('period_type')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('last_event_id')->nullable()->index();
            $table->string('last_event_type')->nullable();
            $table->timestampTz('last_verified_at');
            $table->json('subscriber_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenuecat_entitlements');
    }
};
