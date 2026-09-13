<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('family_name', 80)->nullable()->after('name');
            $table->string('phone_number', 30)->nullable()->after('email');
            $table->string('address')->nullable()->after('date_of_birth');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state_province', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state_province');
            $table->string('country', 100)->nullable()->after('postal_code');
            $table->dropColumn('sex');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('sex', 1)->nullable()->after('date_of_birth');
            $table->dropColumn([
                'family_name',
                'phone_number',
                'address',
                'city',
                'state_province',
                'postal_code',
                'country',
            ]);
        });
    }
};
