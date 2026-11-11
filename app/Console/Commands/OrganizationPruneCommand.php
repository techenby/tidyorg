<?php

namespace App\Console\Commands;

use App\Actions\Teams\PurgeTeam;
use App\Models\Team;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('organization:prune')]
#[Description('Permanently delete organizations that were closed long enough ago, and old exports')]
class OrganizationPruneCommand extends Command
{
    /**
     * How long an export stays available, in days.
     */
    public const EXPORT_DAYS = 7;

    /**
     * Execute the console command.
     */
    public function handle(PurgeTeam $purge): int
    {
        $closedBefore = now()->subDays((int) config('tessera.closed_organization_days'));

        $purged = Team::onlyTrashed()
            ->where('deleted_at', '<=', $closedBefore)
            ->get()
            ->each(fn (Team $team) => $purge->handle($team))
            ->count();

        $expired = $this->expiredExports();

        Storage::delete($expired);

        $this->components->info("Purged {$purged} closed organizations and ".count($expired).' old exports.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function expiredExports(): array
    {
        $before = now()->subDays(self::EXPORT_DAYS)->getTimestamp();

        return array_values(collect(Storage::directories('teams'))
            ->flatMap(fn (string $directory): array => Storage::files("{$directory}/exports"))
            ->filter(fn (string $path): bool => Storage::lastModified($path) <= $before)
            ->all());
    }
}
