<?php

namespace App\Console\Commands;

use App\Models\CollectionKind;
use App\Models\CountryState;
use App\Services\UsStates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteUsStateCollection extends Command
{
    protected $signature = 'collections:complete-us-states {collection=usa} {--apply : Add missing state entries after reviewing the dry run}';

    protected $description = 'Complete a US state checklist while preserving existing entries and progress IDs';

    public function handle(): int
    {
        $kind = CollectionKind::with('states')->findOrFail($this->argument('collection'));
        $existing = $kind->states->pluck('normalized_name');
        $missing = [];
        foreach (UsStates::NAMES as $name) {
            $normalized = UsStates::normalize($name);
            if ($existing->contains($normalized)) {
                continue;
            }
            $state = CountryState::where('country_code', 'US')->where('normalized_name', $normalized)->first();
            if (! $state) {
                $this->error("No US state record for {$name}. Add it to the US states listing before applying this repair.");

                return self::FAILURE;
            }
            $missing[] = ['code' => $code, 'name' => $name, 'state' => $state];
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
                $kind->states()->syncWithoutDetaching([$entry['state']->id]);
            }
        });
        $this->info('US state sources updated. Existing collection-list entries were preserved.');

        return self::SUCCESS;
    }
}
