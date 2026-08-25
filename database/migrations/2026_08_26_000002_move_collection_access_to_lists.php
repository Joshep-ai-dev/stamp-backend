<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('collectionlist', 'access')) {
            Schema::table('collectionlist', function (Blueprint $table): void {
                $table->string('access', 16)->default('free')->after('detail');
            });
        }

        if (Schema::hasColumn('collectionkind', 'access')) {
            DB::table('collectionkind')->get(['id', 'access'])->each(function ($kind): void {
                DB::table('collectionlist')->where('collectionkind_id', $kind->id)->update(['access' => $kind->access]);
            });
            Schema::table('collectionkind', function (Blueprint $table): void {
                $table->dropColumn('access');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('collectionkind', 'access')) {
            Schema::table('collectionkind', function (Blueprint $table): void {
                $table->string('access', 16)->default('free')->after('detail');
            });
        }
        DB::table('collectionkind')->whereExists(function ($query): void {
            $query->selectRaw('1')->from('collectionlist')
                ->whereColumn('collectionlist.collectionkind_id', 'collectionkind.id')
                ->where('collectionlist.access', 'pro');
        })->update(['access' => 'pro']);
        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->dropColumn('access');
        });
    }
};
