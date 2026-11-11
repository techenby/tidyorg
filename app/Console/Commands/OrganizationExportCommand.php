<?php

namespace App\Console\Commands;

use App\Actions\Teams\ExportTeam;
use App\Models\Team;
use App\Modules\StoredFile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('organization:export {team : The slug of the organization}')]
#[Description('Write everything stored for an organization to a zip archive')]
class OrganizationExportCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExportTeam $export): int
    {
        $team = Team::withTrashed()->where('slug', $this->argument('team'))->first();

        if (! $team) {
            $this->components->error("Organization [{$this->argument('team')}] was not found.");

            return self::FAILURE;
        }

        $path = $export->handle($team);

        $this->components->info("Exported {$team->name} to [{$path}] on the [".config('filesystems.default').'] disk.');
        $this->components->twoColumnDetail('Download link, valid for '.StoredFile::LINK_LIFETIME.' minutes', (string) StoredFile::url($path));

        return self::SUCCESS;
    }
}
