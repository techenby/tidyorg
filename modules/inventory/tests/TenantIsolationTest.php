<?php

use App\Enums\TeamRole;
use Livewire\Livewire;
use Tessera\Inventory\Models\Item;

/*
 * One organization must never see or change another's inventory. "Ours" is the
 * signed-in user's organization and "theirs" belongs to someone else; both have
 * Inventory enabled, so nothing here passes merely because a module is off.
 */

beforeEach(function () {
    $this->user = ownerWithModules('inventory');
    $this->ours = $this->user->currentTeam;

    $this->theirs = ownerWithModules('inventory')->currentTeam;
    $this->theirItem = Item::factory()->for($this->theirs)->create(['name' => 'Their projector']);
});

test('another organization\'s inventory page is forbidden', function () {
    $this->actingAs($this->user)
        ->get(route('inventory.index', ['current_team' => $this->theirs]))
        ->assertForbidden();
});

test('another organization\'s items are not listed or found by search', function () {
    Livewire::actingAs($this->user)
        ->test('inventory::index')
        ->assertDontSee('Their projector')
        ->set('search', 'projector')
        ->assertDontSee('Their projector')
        ->set('filters.showTrashed', true)
        ->assertDontSee('Their projector');
});

test('another organization\'s item cannot be opened through either organization\'s URL', function () {
    $this->actingAs($this->user)
        ->get(route('inventory.show', ['current_team' => $this->theirs, 'item' => $this->theirItem]))
        ->assertForbidden();

    $this->actingAs($this->user)
        ->get(route('inventory.show', ['current_team' => $this->ours, 'item' => $this->theirItem]))
        ->assertNotFound();
});

test('another organization\'s item cannot be browsed into', function () {
    Item::factory()->childOf($this->theirItem)->create(['name' => 'Their remote']);

    Livewire::withQueryParams(['parentId' => $this->theirItem->id])
        ->actingAs($this->user)
        ->test('inventory::index')
        ->assertDontSee('Their remote')
        ->assertDontSee('Their projector');
});

test('another organization\'s item cannot be changed from the inventory page', function (string $method) {
    $this->theirItem->delete();
    $trashed = $this->theirItem;
    $live = Item::factory()->for($this->theirs)->create();

    $target = in_array($method, ['restore', 'forceDelete'], true) ? $trashed : $live;

    Livewire::actingAs($this->user)
        ->test('inventory::index')
        ->call($method, $target->id)
        ->assertNotFound();

    expect($live->fresh()->trashed())->toBeFalse()
        ->and($trashed->fresh()->trashed())->toBeTrue();
})->with(['open', 'edit', 'delete', 'restore', 'forceDelete', 'showQrCode']);

test('another organization\'s item cannot be duplicated or moved', function () {
    Livewire::actingAs($this->user)
        ->test('inventory::index')
        ->set('duplicateItemId', $this->theirItem->id)
        ->call('duplicate')
        ->assertNotFound();

    Livewire::actingAs($this->user)
        ->test('inventory::index')
        ->set('moveItemId', $this->theirItem->id)
        ->set('moveToTeamId', $this->ours->id)
        ->call('moveToTeam')
        ->assertHasErrors(['moveToTeamId']);

    expect(Item::count())->toBe(1);
});

test('another organization\'s items are ignored by bulk actions', function () {
    $ourBin = Item::factory()->for($this->ours)->bin()->create();

    Livewire::actingAs($this->user)
        ->test('inventory::index')
        ->set('selected', [$this->theirItem->id])
        ->set('bulkParentId', $ourBin->id)
        ->call('updateParent')
        ->call('bulkDelete');

    expect($this->theirItem->fresh())
        ->parent_id->toBeNull()
        ->trashed()->toBeFalse();
});

test('an item cannot be placed inside another organization\'s item', function () {
    $ourItem = Item::factory()->for($this->ours)->create();

    $component = Livewire::actingAs($this->user)->test('inventory::index');

    $component->call('create')
        ->set('form.name', 'Ladder')
        ->set('form.parent_id', $this->theirItem->id)
        ->call('save')
        ->assertHasErrors(['form.parent_id']);

    $component->call('edit', $ourItem->id)
        ->set('form.parent_id', $this->theirItem->id)
        ->call('save')
        ->assertHasErrors(['form.parent_id']);

    $component->set('selected', [$ourItem->id])
        ->set('bulkParentId', $this->theirItem->id)
        ->call('updateParent')
        ->assertHasErrors(['bulkParentId']);

    expect($ourItem->fresh()->parent_id)->toBeNull()
        ->and(Item::where('name', 'Ladder')->exists())->toBeFalse();
});

test('an item cannot be moved into an organization the user does not belong to', function () {
    $ourItem = Item::factory()->for($this->ours)->create();

    Livewire::actingAs($this->user)
        ->test('inventory::index')
        ->call('openMoveModal', $ourItem->id)
        ->set('moveToTeamId', $this->theirs->id)
        ->call('moveToTeam')
        ->assertHasErrors(['moveToTeamId']);

    expect($ourItem->fresh()->team_id)->toBe($this->ours->id);
});

test('a QR link to another organization\'s item looks like it does not exist', function () {
    $this->actingAs($this->user)
        ->get(route('inventory.link', $this->theirItem))
        ->assertNotFound();
});

test('the dashboard widget only shows the current organization\'s items', function () {
    Item::factory()->for($this->ours)->create(['name' => 'Our ladder']);

    Livewire::actingAs($this->user)
        ->test('inventory::widget')
        ->assertSee('Our ladder')
        ->assertDontSee('Their projector');
});

test('someone removed from an organization loses its inventory', function () {
    $former = teammate($this->theirs, TeamRole::Admin);
    $this->theirs->members()->detach($former);

    Livewire::actingAs($former->fresh())
        ->test('inventory::index')
        ->assertForbidden();
});

test('the item policy denies everything to people outside the organization', function (string $ability) {
    expect($this->user->can($ability, $this->theirItem))->toBeFalse();
})->with(['view', 'update', 'delete', 'restore', 'forceDelete']);
