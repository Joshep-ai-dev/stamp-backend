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
        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->foreignId('sight_id')->nullable()->after('city_id')->constrained('sights')->nullOnDelete();
        });

        DB::table('collectionlist')->whereNotNull('city_id')->orderBy('id')->each(function (object $item): void {
            $normalizedTitle = Str::of($item->title)->ascii()->lower()->squish()->toString();
            $sightId = DB::table('sights')->where('city_id', $item->city_id)->get(['id', 'name'])
                ->first(fn (object $sight) => Str::of($sight->name)->ascii()->lower()->squish()->toString() === $normalizedTitle)?->id;

            if ($sightId) {
                DB::table('collectionlist')->where('id', $item->id)->update(['sight_id' => $sightId]);

                $kindIds = DB::table('collection_kind_lists')->where('collection_list_id', $item->id)->pluck('collection_kind_id')
                    ->push($item->collectionkind_id)->filter()->unique();
                foreach ($kindIds as $kindId) {
                    $legacyId = "collection-{$kindId}-{$item->id}";
                    DB::table('completions')->where('sight_id', $legacyId)->orderBy('id')->each(function (object $completion) use ($sightId): void {
                        $alreadyCompleted = DB::table('completions')->where('user_id', $completion->user_id)->where('sight_id', (string) $sightId)->exists();
                        if ($alreadyCompleted) {
                            DB::table('completions')->where('id', $completion->id)->delete();
                        } else {
                            DB::table('completions')->where('id', $completion->id)->update(['sight_id' => (string) $sightId]);
                        }
                    });
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('collectionlist', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sight_id');
        });
    }
};
