<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_kind_lists', function (Blueprint $table): void {
            $table->string('collection_kind_id');
            $table->string('collection_list_id');
            $table->timestamps();
            $table->primary(['collection_kind_id', 'collection_list_id']);
            $table->foreign('collection_kind_id')->references('id')->on('collectionkind')->cascadeOnDelete();
            $table->foreign('collection_list_id')->references('id')->on('collectionlist')->cascadeOnDelete();
        });

        DB::table('collectionlist')->orderBy('id')->each(function (object $item): void {
            DB::table('collection_kind_lists')->insert([
                'collection_kind_id' => $item->collectionkind_id,
                'collection_list_id' => $item->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // The legacy column remains as a backwards-compatible primary
        // membership. The pivot is the source of truth for memberships.
        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->dropForeign(['collectionkind_id']);
        });
    }

    public function down(): void
    {
        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->foreign('collectionkind_id')->references('id')->on('collectionkind')->cascadeOnDelete();
        });
        Schema::dropIfExists('collection_kind_lists');
    }
};
