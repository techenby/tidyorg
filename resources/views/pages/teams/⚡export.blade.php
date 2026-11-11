<?php

use App\Jobs\ExportTeamData;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Team $team;

    public function export(): void
    {
        Gate::authorize('export', $this->team);

        $allowed = RateLimiter::attempt("team-export:{$this->team->id}", 3, function (): void {
            ExportTeamData::dispatch($this->team, Auth::user());
        }, 3600);

        if (! $allowed) {
            Flux::toast(variant: 'danger', text: __('An export was requested several times in the last hour. Try again later.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Your export is being prepared. We will email you a link when it is ready.'));
    }
}; ?>

<div class="space-y-6" data-test="team-export">
    <div>
        <flux:heading>{{ __('Export data') }}</flux:heading>
        <flux:subheading>{{ __('Get a copy of everything this organization has stored, as a zip file. We email you a link when it is ready.') }}</flux:subheading>
    </div>

    <flux:button wire:click="export" icon="arrow-down-tray" data-test="team-export-button">
        {{ __('Export data') }}
    </flux:button>
</div>
