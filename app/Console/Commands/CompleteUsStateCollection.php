<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\CollectionKind;
use App\Models\CollectionList;
use App\Services\UsStates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteUsStateCollection extends Command
{
    protected $signature = 'collections:complete-us-states {collection=usa} {--apply : Add missing state entries after reviewing the dry run}';

    protected $description = 'Complete a US state checklist while preserving existing entries and progress IDs';

    public function handle(): int
    {
        $kind = CollectionKind::with('lists')->findOrFail($this->argument('collection'));
        $existing = $kind->lists->map(fn ($item) => UsStates::normalize($item->title));
        $cities = City::where('country_code', 'US')->orderByDesc('population')->orderBy('id')->get();
        $missing = [];
        foreach (UsStates::NAMES as $code => $name) {
            if ($existing->contains(UsStates::normalize($name))) {
                continue;
            }
            $city = $cities->first(fn ($city) => UsStates::normalize($city->subcountry) === UsStates::normalize($name));
            if (! $city) {
                $this->error("No catalog city for {$name}. Import its city data before applying this repair.");

                return self::FAILURE;
            }
            $missing[] = ['code' => $code, 'name' => $name, 'city' => $city];
        }
        foreach ($missing as $entry) {
            $this->line('Missing: '.$entry['name']);
        }
        $this->info(count($missing).' state entries to add.');
        if (! $this->option('apply')) {
            $this->line('Dry run only. Run with --apply to add these entries.');

            return self::SUCCESS;
        }
        DB::transaction(function () use ($kind, $missing): void {
            foreach ($missing as $entry) {
                $item = CollectionList::firstOrCreate(['id' => $kind->id.'-state-'.strtolower($entry['code'])], [
                    'collectionkind_id' => $kind->id,
                    'title' => $entry['name'],
                    'city_id' => $entry['city']->id,
                    'location' => $entry['name'].', United States',
                    'access' => 'free',
                ]);
                $kind->lists()->syncWithoutDetaching([$item->id]);
            }
        });
        $this->info('State checklist updated. Existing entries were preserved.');

        return self::SUCCESS;
    }
}
