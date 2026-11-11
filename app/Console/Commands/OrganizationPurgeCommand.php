<?php

namespace App\Console\Commands;

use App\Actions\Teams\PurgeTeam;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('organization:purge
    {team : The slug of the organization}
    {--force : Skip confirmation}')]
#[Description('Permanently delete an organization and everything stored for it')]
class OrganizationPurgeCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PurgeTeam $purge): int
    {
        $team = Team::withTrashed()->where('slug', $this->argument('team'))->first();

        if (! $team) {
            $this->components->error("Organization [{$this->argument('team')}] was not found.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Permanently delete {$team->name} and everything stored for it?")) {
            return self::FAILURE;
        }

        $purge->handle($team);

        $this->components->info("Purged {$team->name}.");

        return self::SUCCESS;
    }
}
