<?php

namespace App\Jobs;

use App\Actions\Teams\ExportTeam;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Teams\TeamExportReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExportTeamData implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Team $team, public User $requestedBy)
    {
        //
    }

    /**
     * Build the export and email the person who asked for it a link to download it.
     */
    public function handle(ExportTeam $export): void
    {
        $path = $export->handle($this->team);

        $this->requestedBy->notify(new TeamExportReady($this->team, basename($path)));
    }
}
