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
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable()->change());
        DB::table('users')->where('email', 'like', '%@members.kroo.invalid')->update(['email' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('email')->orderBy('id')->eachById(function ($user): void {
            DB::table('users')->where('id', $user->id)->update([
                'email' => Str::uuid().'@members.kroo.invalid',
            ]);
        });
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
    }
};
