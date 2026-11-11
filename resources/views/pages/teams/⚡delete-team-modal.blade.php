<?php

use App\Data\UserTeam;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public Team $team;

    public string $deleteName = '';

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    #[Computed]
    public function deleteConfirmLabel(): string
    {
        return __('Type ":name" to confirm', ['name' => $this->team->name]);
    }

    public function deleteTeam(): void
    {
        Gate::authorize('delete', $this->team);

        $validated = $this->validate([
            'deleteName' => ['required', 'string'],
        ]);

        if ($validated['deleteName'] !== $this->team->name) {
            $this->addError('deleteName', __('The organization name does not match.'));

            return;
        }

        DB::transaction(function () {
            User::where('current_team_id', $this->team->id)
                ->each(fn (User $affectedUser) => $affectedUser->switchToFallbackTeam($this->team));

            $this->team->invitations()->delete();
            $this->team->memberships()->delete();
            $this->team->delete();
        });

        Flux::toast(variant: 'success', text: __('Organization deleted.'));

        $this->redirectRoute('teams.index', navigate: true);
    }

    /**
     * @return Collection<int, UserTeam>
     */
    #[Computed]
    public function otherTeams(): Collection
    {
        return Auth::user()->toUserTeams();
    }
}; ?>

<flux:modal name="delete-team" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="deleteTeam" class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Are you sure?') }}</flux:heading>
            <flux:subheading>
                {{ trans_choice('{0} This cannot be undone. ":name" is closed straight away, and everything stored for it is permanently deleted within a day.|{1} This cannot be undone. ":name" is closed straight away, and everything stored for it is permanently deleted after :count day.|[2,*] This cannot be undone. ":name" is closed straight away, and everything stored for it is permanently deleted after :count days.', (int) config('tessera.closed_organization_days'), ['name' => $team->name]) }}
            </flux:subheading>
        </div>

        <div class="space-y-4">
            <flux:input wire:model="deleteName" :label="$this->deleteConfirmLabel" required data-test="delete-team-name" />
        </div>

        <div class="flex justify-end space-x-2 rtl:space-x-reverse">
            <flux:modal.close>
                <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" type="submit" data-test="delete-team-confirm">
                {{ __('Delete organization') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
