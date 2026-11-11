<?php

namespace TidyOrg\Inventory\Livewire\Forms;

use App\Models\Team;
use App\Modules\StoredFile;
use Closure;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;
use TidyOrg\Inventory\Actions\CreateItem;
use TidyOrg\Inventory\Actions\UpdateItem;
use TidyOrg\Inventory\Enums\ItemType;
use TidyOrg\Inventory\Models\Item;

class ItemForm extends Form
{
    public ?Item $editingItem = null;

    public string $name = '';

    public ?string $type = null;

    public ?int $parent_id = null;

    /** @var array<int, array{key: string, value: string}> */
    public array $metadata = [];

    /** @var TemporaryUploadedFile|null */
    public $photo = null;

    public ?string $existingPhotoUrl = null;

    public bool $removePhoto = false;

    /**
     * Whether the form has been opened, so pages only load its options when it is in use.
     */
    public bool $open = false;

    public function start(?int $parentId, string $type): void
    {
        $this->reset();

        $this->fill(['open' => true, 'parent_id' => $parentId, 'type' => $type]);
    }

    public function load(Item $item): void
    {
        $this->reset();

        $this->fill([
            'open' => true,
            'editingItem' => $item,
            'name' => $item->name,
            'type' => $item->type->value,
            'parent_id' => $item->parent_id,
            'metadata' => collect($item->metadata ?? [])
                ->map(fn (string $value, string $key): array => ['key' => $key, 'value' => $value])
                ->values()
                ->all(),
            'existingPhotoUrl' => StoredFile::url($item->photo_path),
        ]);
    }

    public function addMetadata(): void
    {
        $this->metadata[] = ['key' => '', 'value' => ''];
    }

    public function removeMetadata(int $index): void
    {
        unset($this->metadata[$index]);

        $this->metadata = array_values($this->metadata);
    }

    public function clearPhoto(): void
    {
        if ($this->photo) {
            $this->photo->delete();
            $this->photo = null;
        } elseif ($this->existingPhotoUrl) {
            $this->existingPhotoUrl = null;
            $this->removePhoto = true;
        }
    }

    /**
     * Create the item in the given organization, or update the one being edited.
     */
    public function save(Team $team): Item
    {
        $data = $this->validate($this->rulesFor($team));

        $data['parent_id'] = $data['parent_id'] ?: null;

        $data['metadata'] = collect($this->metadata)
            ->filter(fn (array $pair): bool => filled($pair['key']))
            ->mapWithKeys(fn (array $pair): array => [$pair['key'] => $pair['value']])
            ->all() ?: null;

        $item = $this->editingItem
            ? app(UpdateItem::class)->handle($this->editingItem, $data, $this->removePhoto)
            : app(CreateItem::class)->handle($team, $data);

        $this->reset();

        return $item;
    }

    /**
     * The parent must be a live item in the same organization, and never the
     * item itself or something inside it.
     *
     * @return array<string, mixed>
     */
    protected function rulesFor(Team $team): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ItemType::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Item::class, 'id')->where('team_id', $team->id)->withoutTrashed(),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $item = $this->editingItem;

                    if ($item && ((int) $value === $item->id || $item->descendantIds()->contains((int) $value))) {
                        $fail(__('An item cannot be placed inside itself.'));
                    }
                },
            ],
            'metadata' => ['nullable', 'array'],
            'metadata.*.key' => ['nullable', 'string', 'max:255', 'distinct'],
            'metadata.*.value' => ['required_with:metadata.*.key', 'nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
