<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_gifts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('buyer_id')->constrained('users');
            $table->string('recipient_email', 254);
            $table->text('message')->nullable();
            $table->string('product_id');
            $table->json('baseline_transactions');
            $table->string('transaction_id')->nullable()->unique();
            $table->string('code_hash', 64)->nullable()->unique();
            $table->text('encrypted_code')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('redeemed_by')->nullable()->constrained('users');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('membership_gifts'); }
};
