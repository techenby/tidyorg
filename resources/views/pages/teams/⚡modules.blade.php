<?php

use App\Actions\Modules\DisableModule;
use App\Actions\Modules\EnableModule;
use App\Models\Team;
use App\Modules\Facades\Modules;
use App\Modules\Module;
use App\Modules\ModuleException;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public Team $team;

    public function toggle(string $key, EnableModule $enable, DisableModule $disable): void
    {
        Gate::authorize('update', $this->team);

        try {
            Modules::enabled($key, $this->team)
                ? $disable->handle($this->team, $key)
                : $enable->handle($this->team, $key);
        } catch (ModuleException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirectRoute('teams.edit', ['team' => $this->team->slug], navigate: true);
    }

    /**
     * @return list<array{key: string, name: string, description: string, icon: string, enabled: bool}>
     */
    #[Computed]
    public function modules(): array
    {
        return Modules::active()
            ->map(fn (Module $module) => [
                'key' => $module->key(),
                'name' => $module->name(),
                'description' => $module->description(),
                'icon' => $module->icon(),
                'enabled' => Modules::enabled($module->key(), $this->team),
            ])
            ->values()
            ->all();
    }
}; ?>

<div>
    @if ($this->modules !== [] && ! config('tessera.single_organization'))
        <div class="space-y-6" data-test="team-modules">
            <div>
                <flux:heading>{{ __('Modules') }}</flux:heading>
                <flux:subheading>{{ __('Choose what this organization uses. Turning a module off hides it and keeps its data.') }}</flux:subheading>
            </div>

            <div class="space-y-3">
                @foreach ($this->modules as $module)
                    <div wire:key="module-{{ $module['key'] }}" class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="module-row">
                        <div class="flex items-center gap-4">
                            <flux:icon :name="$module['icon']" class="size-5 shrink-0 text-zinc-500 dark:text-zinc-400" />

                            <div>
                                <span class="font-medium">{{ __($module['name']) }}</span>
                                @if ($module['description'] !== '')
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __($module['description']) }}</flux:text>
                                @endif
                            </div>
                        </div>

                        <flux:switch
                            :checked="$module['enabled']"
                            wire:click="toggle('{{ $module['key'] }}')"
                            :aria-label="__($module['name'])"
                            data-test="module-toggle-{{ $module['key'] }}"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
