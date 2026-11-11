<?php

use App\Models\Team;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Tessera\Members\Livewire\Forms\MemberForm;
use Tessera\Members\Models\Member;

new #[Title('Members')] class extends Component {
    use WithPagination;

    public MemberForm $form;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', [Member::class, $this->team]);
    }

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam ?? abort(404);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->hasTeamPermission($this->team, 'members:manage');
    }

    /**
     * @return LengthAwarePaginator<int, Member>
     */
    #[Computed]
    public function members(): LengthAwarePaginator
    {
        $term = '%'.Str::lower($this->search).'%';

        return Member::forTeam($this->team)
            ->when(filled($this->search), fn ($query) => $query->where(fn ($query) => $query
                ->whereRaw('lower(name) like ?', [$term])
                ->orWhereRaw('lower(email) like ?', [$term])))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('create', [Member::class, $this->team]);

        $this->form->reset();
        $this->resetValidation();
        $this->modal('member-form')->show();
    }

    public function edit(int $id): void
    {
        $member = Member::forTeam($this->team)->findOrFail($id);

        Gate::authorize('update', $member);

        $this->form->load($member);
        $this->resetValidation();
        $this->modal('member-form')->show();
    }

    public function save(): void
    {
        $this->form->editingMember
            ? Gate::authorize('update', $this->form->editingMember)
            : Gate::authorize('create', [Member::class, $this->team]);

        $this->form->save($this->team);

        $this->modal('member-form')->close();
    }

    public function delete(int $id): void
    {
        $member = Member::forTeam($this->team)->findOrFail($id);

        Gate::authorize('delete', $member);

        $member->delete();

        Flux::toast(variant: 'success', text: __(':name removed.', ['name' => $member->name]));
    }
}; ?>

<div class="space-y-6" data-test="members-index">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Members') }}</flux:heading>
            <flux:text class="mt-1">{{ __('The people your organization works with. They do not need an account.') }}</flux:text>
        </div>

        @if ($this->canManage)
            <flux:button variant="primary" icon="plus" wire:click="create" data-test="members-add">{{ __('Add member') }}</flux:button>
        @endif
    </div>

    <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search by name or email...')" icon="magnifying-glass" class="max-w-sm" data-test="members-search" />

    <flux:table :paginate="$this->members">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Joined') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->members as $member)
                <flux:table.row :key="$member->id" data-test="members-row">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="xs" :name="$member->name" />
                            <span class="font-medium text-zinc-800 dark:text-white">{{ $member->name }}</span>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($member->email)
                            <flux:link :href="'mailto:'.$member->email" variant="subtle">{{ $member->email }}</flux:link>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $member->phone }}</flux:table.cell>
                    <flux:table.cell>{{ $member->joined_on?->toFormattedDateString() }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @if ($this->canManage)
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('Actions')" data-test="members-row-actions" />

                                <flux:menu>
                                    <flux:menu.item wire:click="edit({{ $member->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item wire:click="delete({{ $member->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Remove this member? Their record is deleted permanently.') }}">{{ __('Remove') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty">
                    <flux:table.cell colspan="5" class="text-center">
                        <div class="py-8">
                            <flux:text size="lg">{{ filled($search) ? __('No members found') : __('No members yet.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="member-form" variant="flyout" class="md:w-96">
        <form wire:submit="save" class="space-y-6" data-test="members-form">
            <flux:heading size="lg">{{ $form->editingMember ? __('Edit member') : __('Add member') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" type="text" required data-test="members-name" />
            <flux:input wire:model="form.email" :label="__('Email')" type="email" />
            <flux:input wire:model="form.phone" :label="__('Phone')" type="tel" />
            <flux:input wire:model="form.joined_on" :label="__('Joined')" type="date" :max="now()->toDateString()" />
            <flux:textarea wire:model="form.notes" :label="__('Notes')" rows="4" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="members-save">{{ $form->editingMember ? __('Update') : __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
