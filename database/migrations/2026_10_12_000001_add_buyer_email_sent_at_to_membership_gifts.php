<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_gifts', function (Blueprint $table): void {
            $table->timestamp('buyer_email_sent_at')->nullable();
        });
        // Do not send retroactive confirmations for gifts already delivered.
        DB::table('membership_gifts')->whereNotNull('email_sent_at')->update(['buyer_email_sent_at' => DB::raw('email_sent_at')]);
    }

    public function down(): void
    {
        Schema::table('membership_gifts', fn (Blueprint $table) => $table->dropColumn('buyer_email_sent_at'));
    }
};
