<?php

namespace Tessera\Inventory\Livewire;

use App\Models\Team;
use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Facades\Modules;
use App\Modules\Person;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Tessera\Inventory\Actions\GenerateItemQrCode;
use Tessera\Inventory\Livewire\Forms\ItemForm;
use Tessera\Inventory\Models\Item;

/**
 * What the inventory pages share: the current organization, what the user may
 * do in it, and the add/edit form with its photo and metadata controls.
 *
 * @property-read Team $team
 * @property-read bool $canManage
 * @property-read bool $canDelete
 * @property-read Collection<int, Team> $otherTeams
 * @property-read Collection<int, Item> $parentItems
 * @property-read PeopleDirectory|null $directory
 */
abstract class InventoryPage extends Component
{
    use WithFileUploads, WithPagination;

    public ItemForm $form;

    /** @var array{svg: string, name: string, url: string}|null */
    public ?array $qrCode = null;

    #[Computed]
    public function team(): Team
    {
        return Auth::user()->currentTeam ?? abort(404);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Auth::user()->hasTeamPermission($this->team, 'inventory:manage');
    }

    #[Computed]
    public function canDelete(): bool
    {
        return Auth::user()->hasTeamPermission($this->team, 'inventory:delete');
    }

    /**
     * The organization's people directory, when a module enabled for it provides one.
     *
     * Checking items out is offered only when this is not null. Inventory does
     * not know or care which module the directory comes from.
     */
    #[Computed]
    public function directory(): ?PeopleDirectory
    {
        return Modules::capability(PeopleDirectory::class, $this->team);
    }

    /**
     * The people who have the given items, keyed by person id.
     *
     * @param  iterable<Item>  $items
     * @return SupportCollection<int, Person>
     */
    protected function borrowersOf(iterable $items): SupportCollection
    {
        $ids = collect($items)->pluck('checked_out_to')->filter()->unique()->values()->all();

        if ($this->directory === null || $ids === []) {
            return collect();
        }

        return $this->directory->find($this->team, $ids);
    }

    /**
     * The other organizations the user may move items into.
     *
     * @return Collection<int, Team>
     */
    #[Computed]
    public function otherTeams(): Collection
    {
        $user = Auth::user();

        return $user->teams()
            ->whereKeyNot($this->team->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (Team $team): bool => $user->hasTeamPermission($team, 'inventory:manage'))
            ->values();
    }

    /**
     * The items the one in the form could be placed inside, once the form is open.
     *
     * @return Collection<int, Item>
     */
    #[Computed]
    public function parentItems(): Collection
    {
        if (! $this->form->open) {
            return new Collection;
        }

        $editing = $this->form->editingItem;

        return Item::forTeam($this->team)
            ->when($editing, fn ($query) => $query->whereNotIn('id', [$editing->id, ...$editing->descendantIds()]))
            ->orderBy('name')
            ->get();
    }

    public function addMetadata(): void
    {
        $this->form->addMetadata();
    }

    public function removeMetadata(int $index): void
    {
        $this->form->removeMetadata($index);
    }

    public function removePhoto(): void
    {
        $this->form->clearPhoto();
    }

    /**
     * Authorize and save the form, returning the saved item.
     */
    protected function saveForm(): Item
    {
        $this->form->editingItem
            ? Gate::authorize('update', $this->form->editingItem)
            : Gate::authorize('create', [Item::class, $this->team]);

        return $this->form->save($this->team);
    }

    protected function openQrCode(Item $item): void
    {
        Gate::authorize('view', $item);

        $this->qrCode = app(GenerateItemQrCode::class)->handle($item);

        $this->modal('qr-code')->show();
    }
}
