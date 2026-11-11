<?php

use TidyOrg\Inventory\Models\Item;

beforeEach(function () {
    $this->owner = ownerWithModules('inventory');
    $this->team = $this->owner->currentTeam;
});

test('guests are sent to log in and then back to the link', function () {
    $item = Item::factory()->for($this->team)->create();

    $this->get(route('inventory.link', $item))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('inventory.link', $item));
});

test('a link to an item without contents opens the item', function () {
    $item = Item::factory()->for($this->team)->create();

    $this->actingAs($this->owner)
        ->get(route('inventory.link', $item))
        ->assertRedirect(route('inventory.show', ['current_team' => $this->team, 'item' => $item]));
});

test('a link to an item with contents opens its contents', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    Item::factory()->childOf($bin)->create();

    $this->actingAs($this->owner)
        ->get(route('inventory.link', $bin))
        ->assertRedirect(route('inventory.index', ['current_team' => $this->team, 'parentId' => $bin->id]));
});

test('a link to a deleted item opens the item', function () {
    $bin = Item::factory()->for($this->team)->bin()->create();
    Item::factory()->childOf($bin)->create();
    $bin->delete();

    $this->actingAs($this->owner)
        ->get(route('inventory.link', $bin))
        ->assertRedirect(route('inventory.show', ['current_team' => $this->team, 'item' => $bin]));
});

test('a link stops working while the module is disabled for the organization', function () {
    $item = Item::factory()->for($this->team)->create();
    $this->team->modules()->delete();

    $this->actingAs($this->owner)
        ->get(route('inventory.link', $item))
        ->assertNotFound();
});

test('an organization named "i" still reaches its inventory', function () {
    $this->team->update(['name' => 'I']);

    $this->actingAs($this->owner)
        ->get('/i/inventory')
        ->assertOk();
});
