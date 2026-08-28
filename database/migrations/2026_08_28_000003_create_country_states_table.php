<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_states', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2);
            $table->string('name', 150);
            $table->string('normalized_name', 150);
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->unique(['country_code', 'normalized_name']);
            $table->index(['country_code', 'name']);
        });

        DB::table('cities')->whereNotNull('subcountry')->where('subcountry', '!=', '')
            ->select(['country_code', 'subcountry'])->distinct()->orderBy('country_code')
            ->get()
            ->each(function ($city): void {
                DB::table('country_states')->insertOrIgnore([
                    'country_code' => $city->country_code,
                    'name' => $city->subcountry,
                    'normalized_name' => Str::of($city->subcountry)->ascii()->lower()->squish()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_states');
    }
};
