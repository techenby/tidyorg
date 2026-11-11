<?php

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use TidyOrg\Inventory\Enums\ItemType;
use TidyOrg\Inventory\Models\Item;

beforeEach(function () {
    $this->owner = ownerWithModules('inventory');
    $this->team = $this->owner->currentTeam;
});

test('the inventory page lists top-level items for the organization', function () {
    $garage = Item::factory()->for($this->team)->location()->create(['name' => 'Warehouse']);
    Item::factory()->childOf($garage)->create(['name' => 'Folding table']);

    $this->actingAs($this->owner)
        ->get(route('inventory.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertSee('Warehouse')
        ->assertDontSee('Folding table');
});

test('guests are sent to the login page', function () {
    $this->get(route('inventory.index', ['current_team' => $this->team]))
        ->assertRedirect(route('login'));
});

test('the inventory page does not exist while the module is disabled for the organization', function () {
    $this->team->modules()->delete();

    $this->actingAs($this->owner)
        ->get(route('inventory.index', ['current_team' => $this->team]))
        ->assertNotFound();
});

test('opening an item with contents browses into it', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    Item::factory()->childOf($bin)->create(['name' => 'Extension cord']);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('open', $bin->id)
        ->assertSet('parentId', $bin->id)
        ->assertSee('Extension cord')
        ->call('navigateUp')
        ->assertSet('parentId', null);
});

test('opening an item without contents goes to its page', function () {
    $item = Item::factory()->for($this->team)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('open', $item->id)
        ->assertRedirect(route('inventory.show', ['current_team' => $this->team, 'item' => $item]));
});

test('search finds items at any depth, whatever the letter case', function () {
    $bin = Item::factory()->for($this->team)->bin()->create(['name' => 'Blue bin']);
    Item::factory()->childOf($bin)->create(['name' => 'Extension Cord']);
    Item::factory()->for($this->team)->create(['name' => 'Ladder']);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('search', 'extension')
        ->assertSee('Extension Cord')
        ->assertSee('in Blue bin')
        ->assertDontSee('Ladder');
});

test('items can be filtered by type', function () {
    Item::factory()->for($this->team)->location()->create(['name' => 'Warehouse']);
    Item::factory()->for($this->team)->create(['name' => 'Ladder']);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('filters.types', [ItemType::Location->value])
        ->assertSee('Warehouse')
        ->assertDontSee('Ladder');
});

test('the sort column cannot be set to anything but a known column', function () {
    Item::factory()->for($this->team)->create(['name' => 'Ladder']);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('sortBy', 'name; drop table inventory_items')
        ->assertSee('Ladder');
});

test('an item can be created inside the item being browsed', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('parentId', $bin->id)
        ->call('create')
        ->set('form.name', 'Extension cord')
        ->set('form.metadata', [['key' => 'serial', 'value' => 'A-100'], ['key' => '', 'value' => '']])
        ->call('save')
        ->assertHasNoErrors();

    $item = Item::firstWhere('name', 'Extension cord');

    expect($item->team_id)->toBe($this->team->id)
        ->and($item->parent_id)->toBe($bin->id)
        ->and($item->type)->toBe(ItemType::Item)
        ->and($item->metadata)->toBe(['serial' => 'A-100']);
});

test('an item needs a name and a known type', function () {
    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('create')
        ->set('form.name', '')
        ->set('form.type', 'spaceship')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.type']);

    expect(Item::count())->toBe(0);
});

test('an uploaded photo is stored with a thumbnail and can be removed', function () {
    Storage::fake();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('create')
        ->set('form.name', 'Stand mixer')
        ->set('form.photo', UploadedFile::fake()->image('mixer.jpg', 800, 600))
        ->call('save')
        ->assertHasNoErrors();

    $item = Item::firstWhere('name', 'Stand mixer');
    [$photo, $thumb] = [$item->photo_path, $item->thumb_path];

    expect($photo)->toStartWith("teams/{$this->team->id}/inventory/")
        ->and($thumb)->not->toBeNull()
        ->and($item->photo_url)->not->toBeNull();

    Storage::assertExists([$photo, $thumb]);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('edit', $item->id)
        ->call('removePhoto')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->photo_path)->toBeNull()
        ->and($item->fresh()->thumb_path)->toBeNull();

    Storage::assertMissing([$photo, $thumb]);
});

test('a photo must be an image', function () {
    Storage::fake();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('create')
        ->set('form.name', 'Stand mixer')
        ->set('form.photo', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['form.photo']);
});

test('an item can be edited', function () {
    $shelf = Item::factory()->for($this->team)->location()->create();
    $item = Item::factory()->for($this->team)->create(['name' => 'Laddr', 'metadata' => ['height' => '6 ft']]);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('edit', $item->id)
        ->assertSet('form.name', 'Laddr')
        ->assertSet('form.metadata', [['key' => 'height', 'value' => '6 ft']])
        ->set('form.name', 'Ladder')
        ->set('form.parent_id', $shelf->id)
        ->call('removeMetadata', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh())
        ->name->toBe('Ladder')
        ->parent_id->toBe($shelf->id)
        ->metadata->toBeNull();
});

test('an item cannot be placed inside itself or its own contents', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    $inner = Item::factory()->childOf($bin)->bin()->create();
    $deepest = Item::factory()->childOf($inner)->create();

    $component = Livewire::actingAs($this->owner)->test('inventory::index')->call('edit', $bin->id);

    $component->set('form.parent_id', $bin->id)->call('save')->assertHasErrors(['form.parent_id']);
    $component->set('form.parent_id', $deepest->id)->call('save')->assertHasErrors(['form.parent_id']);

    expect($bin->fresh()->parent_id)->toBeNull();
});

test('members can add and edit items but not delete them', function () {
    $member = teammate($this->team);
    $item = Item::factory()->for($this->team)->create();

    $component = Livewire::actingAs($member)->test('inventory::index');

    $component->call('create')->set('form.name', 'Ladder')->call('save')->assertHasNoErrors();
    $component->call('delete', $item->id)->assertForbidden();

    expect(Item::where('name', 'Ladder')->exists())->toBeTrue()
        ->and($item->fresh()->trashed())->toBeFalse();
});

test('admins can delete an item, which leaves its contents at the top level', function () {
    $admin = teammate($this->team, TeamRole::Admin);
    $bin = Item::factory()->for($this->team)->bin()->create();
    $child = Item::factory()->childOf($bin)->create();

    Livewire::actingAs($admin)
        ->test('inventory::index')
        ->call('delete', $bin->id)
        ->assertHasNoErrors();

    expect($bin->fresh()->trashed())->toBeTrue()
        ->and($child->fresh()->parent_id)->toBeNull();
});

test('deleted items are listed separately and can be restored', function () {
    $item = Item::factory()->for($this->team)->create(['name' => 'Ladder']);
    $item->delete();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->assertDontSee('Ladder')
        ->set('filters.showTrashed', true)
        ->assertSee('Ladder')
        ->call('restore', $item->id);

    expect($item->fresh()->trashed())->toBeFalse();
});

test('deleting an item forever removes it and its photo', function () {
    Storage::fake();
    Storage::put('teams/1/inventory/ladder.jpg', 'photo');

    $item = Item::factory()->for($this->team)->create(['photo_path' => 'teams/1/inventory/ladder.jpg']);
    $item->delete();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('forceDelete', $item->id);

    expect(Item::withTrashed()->find($item->id))->toBeNull();

    Storage::assertMissing('teams/1/inventory/ladder.jpg');
});

test('members cannot delete an item forever', function () {
    $item = Item::factory()->for($this->team)->create();
    $item->delete();

    Livewire::actingAs(teammate($this->team))
        ->test('inventory::index')
        ->call('forceDelete', $item->id)
        ->assertForbidden();

    expect(Item::withTrashed()->find($item->id))->not->toBeNull();
});

test('selected items can be moved into another item together', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    $items = Item::factory()->for($this->team)->count(2)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('selected', $items->modelKeys())
        ->set('bulkParentId', $bin->id)
        ->call('updateParent')
        ->assertHasNoErrors()
        ->assertSet('selected', []);

    expect($items->fresh()->pluck('parent_id')->all())->toBe([$bin->id, $bin->id]);
});

test('selected items cannot be moved into one of themselves', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    $inner = Item::factory()->childOf($bin)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('selected', [$bin->id])
        ->set('bulkParentId', $inner->id)
        ->call('updateParent')
        ->assertHasErrors(['bulkParentId']);

    expect($bin->fresh()->parent_id)->toBeNull();
});

test('selected items can be deleted and restored together', function () {
    $items = Item::factory()->for($this->team)->count(2)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('selected', $items->modelKeys())
        ->call('bulkDelete');

    expect(Item::count())->toBe(0);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->set('filters.showTrashed', true)
        ->set('selected', $items->modelKeys())
        ->call('bulkRestore');

    expect(Item::count())->toBe(2);
});

test('members cannot delete selected items', function () {
    $items = Item::factory()->for($this->team)->count(2)->create();

    Livewire::actingAs(teammate($this->team))
        ->test('inventory::index')
        ->set('selected', $items->modelKeys())
        ->call('bulkDelete')
        ->assertForbidden();

    expect(Item::count())->toBe(2);
});

test('an item can be duplicated several times', function () {
    $item = Item::factory()->for($this->team)->create(['name' => 'Folding chair']);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('openDuplicateModal', $item->id)
        ->set('duplicateCount', 3)
        ->call('duplicate')
        ->assertHasNoErrors();

    expect(Item::where('name', 'Folding chair')->count())->toBe(4);
});

test('an item cannot be duplicated more than 25 times at once', function () {
    $item = Item::factory()->for($this->team)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('openDuplicateModal', $item->id)
        ->set('duplicateCount', 26)
        ->call('duplicate')
        ->assertHasErrors(['duplicateCount']);

    expect(Item::count())->toBe(1);
});

test('an item can be moved to another organization the user manages inventory for', function () {
    $other = Team::factory()->create();
    $other->members()->attach($this->owner, ['role' => TeamRole::Member->value]);
    $other->modules()->create(['module' => 'inventory']);

    $bin = Item::factory()->for($this->team)->bin()->create();
    $item = Item::factory()->childOf($bin)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('openMoveModal', $item->id)
        ->set('moveToTeamId', $other->id)
        ->call('moveToTeam')
        ->assertHasNoErrors();

    expect($item->fresh())
        ->team_id->toBe($other->id)
        ->parent_id->toBeNull();
});

test('an item cannot be moved to an organization without inventory enabled', function () {
    $other = Team::factory()->create();
    $other->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);

    $item = Item::factory()->for($this->team)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('openMoveModal', $item->id)
        ->set('moveToTeamId', $other->id)
        ->call('moveToTeam')
        ->assertHasErrors(['moveToTeamId']);

    expect($item->fresh()->team_id)->toBe($this->team->id);
});

test('a QR code links back to the item', function () {
    $item = Item::factory()->for($this->team)->create(['name' => 'Ladder']);

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->call('showQrCode', $item->id)
        ->assertSet('qrCode.url', route('inventory.link', $item))
        ->assertSet('qrCode.name', 'Ladder')
        ->assertSeeHtml('<svg');
});
