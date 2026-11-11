<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CloseTeam
{
    /**
     * Close an organization so nobody can open it any more.
     *
     * Its data is kept until the organization is purged, which happens
     * automatically after the configured number of days.
     */
    public function handle(Team $team): void
    {
        DB::transaction(function () use ($team) {
            User::where('current_team_id', $team->id)
                ->each(fn (User $user) => $user->switchToFallbackTeam($team));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });
    }
}
