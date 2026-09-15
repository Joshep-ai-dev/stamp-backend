<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kroo_id_allocations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('kroo_id')->nullable()->unique();
            $table->boolean('email_opt_in')->default(true);
        });

        DB::table('users')->orderBy('created_at')->orderBy('id')->get(['id'])
            ->each(function (object $user): void {
                $krooId = DB::table('kroo_id_allocations')->insertGetId([
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('users')->where('id', $user->id)->update(['kroo_id' => $krooId]);
            });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['kroo_id', 'email_opt_in']));
        Schema::dropIfExists('kroo_id_allocations');
    }
};
