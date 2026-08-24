<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collection_kinds') && ! Schema::hasTable('collectionkind')) {
            Schema::rename('collection_kinds', 'collectionkind');
        }
        if (Schema::hasTable('collection_lists') && ! Schema::hasTable('collectionlist')) {
            Schema::rename('collection_lists', 'collectionlist');
        }
        if (Schema::hasColumn('collectionlist', 'collection_kind_id')) {
            Schema::table('collectionlist', fn (Blueprint $table) => $table->renameColumn('collection_kind_id', 'collectionkind_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('collectionlist', 'collectionkind_id')) {
            Schema::table('collectionlist', fn (Blueprint $table) => $table->renameColumn('collectionkind_id', 'collection_kind_id'));
        }
        if (Schema::hasTable('collectionlist') && ! Schema::hasTable('collection_lists')) {
            Schema::rename('collectionlist', 'collection_lists');
        }
        if (Schema::hasTable('collectionkind') && ! Schema::hasTable('collection_kinds')) {
            Schema::rename('collectionkind', 'collection_kinds');
        }
    }
};
