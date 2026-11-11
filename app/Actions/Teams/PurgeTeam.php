<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeTeam
{
    public function __construct(private ModuleRegistry $modules)
    {
        //
    }

    /**
     * Permanently delete an organization and everything stored for it.
     *
     * Every module that holds data is asked to purge, whether or not it is
     * active or enabled, because switching a module off leaves its data in place.
     */
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team) {
            $this->modules->holdingData()->each(fn (Module $module) => $module->purging($team));

            User::where('current_team_id', $team->id)
                ->each(fn (User $user) => $user->switchToFallbackTeam($team));

            $team->modules()->delete();
            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->forceDelete();
        });

        Storage::deleteDirectory(Team::storageDirectory($team->id));
    }
}
