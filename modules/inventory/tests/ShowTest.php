<?php

use App\Enums\TeamRole;
use App\Models\Team;
use Livewire\Livewire;
use TidyOrg\Inventory\Models\Item;

beforeEach(function () {
    $this->owner = ownerWithModules('inventory');
    $this->team = $this->owner->currentTeam;
});

test('an item page shows its details, where it is, and what it holds', function () {
    $room = Item::factory()->for($this->team)->location()->create(['name' => 'Storage room']);
    $bin = Item::factory()->childOf($room)->bin()->create(['name' => 'Blue bin', 'metadata' => ['colour' => 'Blue']]);
    Item::factory()->childOf($bin)->create(['name' => 'Extension cord']);

    $this->actingAs($this->owner)
        ->get(route('inventory.show', ['current_team' => $this->team, 'item' => $bin]))
        ->assertOk()
        ->assertSee('Blue bin')
        ->assertSee('Storage room')
        ->assertSee('Colour')
        ->assertSee('Extension cord');
});

test('a deleted item page says so and offers to restore it', function () {
    $item = Item::factory()->for($this->team)->create();
    $item->delete();

    $this->actingAs($this->owner)
        ->get(route('inventory.show', ['current_team' => $this->team, 'item' => $item]))
        ->assertOk()
        ->assertSeeHtml('data-test="inventory-deleted-notice"');

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $item])
        ->call('restore');

    expect($item->fresh()->trashed())->toBeFalse();
});

test('an item added from an item page goes inside that item', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $bin])
        ->call('create')
        ->assertSet('form.parent_id', $bin->id)
        ->set('form.name', 'Extension cord')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Extension cord');

    expect($bin->children()->count())->toBe(1);
});

test('an item can be edited from its page', function () {
    $item = Item::factory()->for($this->team)->create(['name' => 'Laddr']);

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $item])
        ->call('edit')
        ->set('form.name', 'Ladder')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Ladder');

    expect($item->fresh()->name)->toBe('Ladder');
});

test('an item can be duplicated from its page', function () {
    $item = Item::factory()->for($this->team)->create(['name' => 'Folding chair']);

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $item])
        ->set('duplicateCount', 2)
        ->call('duplicate')
        ->assertHasNoErrors();

    expect(Item::where('name', 'Folding chair')->count())->toBe(3);
});

test('deleting an item from its page returns to where it was', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    $item = Item::factory()->childOf($bin)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $item])
        ->call('delete')
        ->assertRedirect(route('inventory.index', ['current_team' => $this->team, 'parentId' => $bin->id]));

    expect($item->fresh()->trashed())->toBeTrue();
});

test('members cannot delete an item from its page', function () {
    $item = Item::factory()->for($this->team)->create();

    Livewire::actingAs(teammate($this->team))
        ->test('inventory::show', ['item' => $item])
        ->call('delete')
        ->assertForbidden();

    expect($item->fresh()->trashed())->toBeFalse();
});

test('an item can be moved to another organization from its page', function () {
    $other = Team::factory()->create();
    $other->members()->attach($this->owner, ['role' => TeamRole::Admin->value]);
    $other->modules()->create(['module' => 'inventory']);

    $item = Item::factory()->for($this->team)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $item])
        ->set('moveToTeamId', $other->id)
        ->call('moveToTeam')
        ->assertRedirect(route('inventory.index', ['current_team' => $this->team]));

    expect($item->fresh()->team_id)->toBe($other->id);
});

test('a QR code can be shown from an item page', function () {
    $item = Item::factory()->for($this->team)->create();

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $item])
        ->call('showQrCode')
        ->assertSet('qrCode.url', route('inventory.link', $item));
});
