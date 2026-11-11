<?php

use Livewire\Livewire;
use Tests\Fixtures\Modules\People\FakePeopleDirectory;
use Tests\Fixtures\Modules\People\PeopleModule;
use TidyOrg\Inventory\Actions\DuplicateItem;
use TidyOrg\Inventory\Actions\MoveItemToTeam;
use TidyOrg\Inventory\Models\Item;

/*
 * Checking items out depends on a people directory, which Inventory gets from
 * whichever module provides one. These tests use a stand-in so they pass with
 * or without the Members module installed.
 */

beforeEach(function () {
    FakePeopleDirectory::$people = [];
    installModules([PeopleModule::class]);

    $this->owner = ownerWithModules('inventory', 'people');
    $this->team = $this->owner->currentTeam;
    $this->item = Item::factory()->for($this->team)->create(['name' => 'Projector']);

    FakePeopleDirectory::add($this->team, 7, 'Ada Okafor');
});

test('checking out is not offered without a people directory', function () {
    $this->team->modules()->where('module', 'people')->delete();
    $this->item->update(['checked_out_to' => 7, 'checked_out_at' => now()]);

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $this->item])
        ->assertDontSeeHtml('data-test="inventory-checkout"')
        ->assertDontSee('Ada Okafor');

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->assertDontSeeHtml('data-test="inventory-checked-out"');

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $this->item])
        ->set('checkOutTo', 7)
        ->call('checkOut')
        ->assertNotFound();
});

test('an item can be checked out to someone in the directory and checked back in', function () {
    $this->travelTo('2026-11-10 09:00:00');

    $component = Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $this->item])
        ->assertSee('Available')
        ->set('checkOutTo', 7)
        ->call('checkOut')
        ->assertHasNoErrors()
        ->assertSee('Ada Okafor');

    expect($this->item->fresh())
        ->checked_out_to->toBe(7)
        ->and($this->item->fresh()->checked_out_at->toDateTimeString())->toBe('2026-11-10 09:00:00');

    Livewire::actingAs($this->owner)
        ->test('inventory::index')
        ->assertSeeHtml('data-test="inventory-checked-out"')
        ->assertSee('Ada Okafor');

    $component->call('checkIn')->assertSee('Available');

    expect($this->item->fresh())
        ->checked_out_to->toBeNull()
        ->checked_out_at->toBeNull();
});

test('an item cannot be checked out to someone outside the organization\'s directory', function () {
    $other = ownerWithModules('inventory', 'people')->currentTeam;
    FakePeopleDirectory::add($other, 99, 'Their Person');

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $this->item])
        ->assertDontSee('Their Person')
        ->set('checkOutTo', 99)
        ->call('checkOut')
        ->assertHasErrors(['checkOutTo']);

    expect($this->item->fresh()->checked_out_to)->toBeNull();
});

test('an item checked out to someone who has left the directory can still be checked in', function () {
    $this->item->update(['checked_out_to' => 404, 'checked_out_at' => now()]);

    Livewire::actingAs($this->owner)
        ->test('inventory::show', ['item' => $this->item])
        ->assertSee('someone no longer in the directory')
        ->call('checkIn');

    expect($this->item->fresh()->checked_out_to)->toBeNull();
});

test('copies and items moved to another organization are not checked out', function () {
    $this->item->update(['checked_out_to' => 7, 'checked_out_at' => now()]);

    $copy = app(DuplicateItem::class)->handle($this->item)->first();

    app(MoveItemToTeam::class)->handle($this->item, ownerWithModules('inventory')->currentTeam);

    expect($copy->fresh()->checked_out_to)->toBeNull()
        ->and($this->item->fresh())
        ->checked_out_to->toBeNull()
        ->checked_out_at->toBeNull();
});
