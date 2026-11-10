<?php

use App\Actions\Teams\CreateTeam;
use App\Models\Team;
use App\Rules\TeamName;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Get started')] #[Layout('layouts::auth')] class extends Component {
    public string $name = '';

    public function mount(): void
    {
        $user = Auth::user();

        if ($team = $user->currentTeam ?? $user->switchToFallbackTeam()) {
            $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);
        }
    }

    public function createTeam(CreateTeam $createTeam): void
    {
        Gate::authorize('create', Team::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = $createTeam->handle(Auth::user(), $validated['name']);

        $this->redirectRoute('dashboard', ['current_team' => $team->slug], navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Create your organization')"
        :description="__('Name the organization you will manage here. You can invite others once it is set up.')"
    />

    <livewire:pages::teams.pending-invitations-modal />

    <form wire:submit="createTeam" class="flex flex-col gap-6">
        <flux:input wire:model="name" :label="__('Organization name')" type="text" required autofocus data-test="onboarding-team-name" />

        <flux:button variant="primary" type="submit" class="w-full" data-test="onboarding-create-team">
            {{ __('Create organization') }}
        </flux:button>
    </form>

    <flux:text class="text-center">
        {{ __('Expecting an invitation? Ask the person who invited you to send it to :email.', ['email' => auth()->user()->email]) }}
    </flux:text>

    <form method="POST" action="{{ route('logout') }}" class="text-center">
        @csrf
        <flux:button variant="ghost" type="submit" class="cursor-pointer text-sm" data-test="logout-button">
            {{ __('Log out') }}
        </flux:button>
    </form>
</div>
