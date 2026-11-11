<?php

use App\Actions\Teams\ExportTeam;
use App\Actions\Teams\PurgeTeam;
use App\Enums\TeamRole;
use App\Modules\Facades\Modules;
use Illuminate\Support\Facades\Storage;
use Tessera\Inventory\Actions\CreateThumbnail;
use Tessera\Inventory\Actions\DuplicateItem;
use Tessera\Inventory\Actions\MoveItemToTeam;
use Tessera\Inventory\Models\Item;

test('the module is discovered and describes itself', function () {
    $module = Modules::find('inventory');

    expect($module)->not->toBeNull()
        ->and($module->permissions())->toBe([
            'inventory:view' => TeamRole::Member,
            'inventory:manage' => TeamRole::Member,
            'inventory:delete' => TeamRole::Admin,
        ]);
});

test('inventory appears in the navigation and on the dashboard once enabled', function () {
    $owner = ownerWithModules('inventory');
    Item::factory()->for($owner->currentTeam)->create(['name' => 'Ladder']);

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $owner->currentTeam]))
        ->assertOk()
        ->assertSee(route('inventory.index', ['current_team' => $owner->currentTeam]))
        ->assertSeeHtml('data-test="inventory-widget"')
        ->assertSee('Ladder');
});

test('inventory is absent from the navigation and dashboard until enabled', function () {
    $owner = ownerWithModules();

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $owner->currentTeam]))
        ->assertOk()
        ->assertDontSee(route('inventory.index', ['current_team' => $owner->currentTeam]))
        ->assertDontSeeHtml('data-test="inventory-widget"');
});

test('purging an organization removes its items and photos and leaves other organizations alone', function () {
    Storage::fake();

    $team = ownerWithModules('inventory')->currentTeam;
    $other = ownerWithModules('inventory')->currentTeam;

    $path = Item::storageDirectory($team->id).'/ladder.jpg';
    Storage::put($path, 'photo');

    Item::factory()->for($team)->create(['photo_path' => $path]);
    Item::factory()->for($team)->create()->delete();
    $kept = Item::factory()->for($other)->create();

    $this->artisan('module:purge', ['key' => 'inventory', '--team' => $team->slug, '--force' => true])
        ->assertSuccessful();

    expect(Item::withTrashed()->pluck('id')->all())->toBe([$kept->id]);

    Storage::assertMissing($path);
});

test('descendants are found at every depth and stop at the item itself', function () {
    $room = Item::factory()->location()->create();
    $bin = Item::factory()->childOf($room)->bin()->create();
    $item = Item::factory()->childOf($bin)->create();
    Item::factory()->for($room->team)->create();

    expect($room->descendantIds()->sort()->values()->all())->toBe([$bin->id, $item->id])
        ->and($item->descendantIds()->all())->toBe([]);
});

test('duplicating an item copies its photo and thumbnail to new files', function () {
    Storage::fake();
    Storage::put('teams/1/inventory/mixer-1.jpg', 'photo');
    Storage::put('teams/1/inventory/thumbs/mixer-1-abc.jpg', 'thumb');

    $item = Item::factory()->create([
        'name' => 'Mixer',
        'photo_path' => 'teams/1/inventory/mixer-1.jpg',
        'thumb_path' => 'teams/1/inventory/thumbs/mixer-1-abc.jpg',
    ]);

    $copy = app(DuplicateItem::class)->handle($item)->first();

    expect($copy->photo_path)->not->toBe($item->photo_path)
        ->and($copy->thumb_path)->not->toBe($item->thumb_path);

    Storage::assertExists([$item->photo_path, $item->thumb_path, $copy->photo_path, $copy->thumb_path]);
});

test('moving an item to another organization takes its photo and leaves its contents behind', function () {
    Storage::fake();

    $bin = Item::factory()->bin()->create();
    $child = Item::factory()->childOf($bin)->create();
    $destination = ownerWithModules('inventory')->currentTeam;

    $path = Item::storageDirectory($bin->team_id).'/bin.jpg';
    Storage::put($path, 'photo');
    $bin->update(['photo_path' => $path]);

    app(MoveItemToTeam::class)->handle($bin, $destination);

    expect($bin->fresh()->photo_path)->toBe(Item::storageDirectory($destination->id).'/bin.jpg')
        ->and($child->fresh())
        ->team_id->not->toBe($destination->id)
        ->parent_id->toBeNull();

    Storage::assertExists($bin->fresh()->photo_path);
    Storage::assertMissing($path);
});

test('a thumbnail is not created for a file that is not an image', function () {
    Storage::fake();
    Storage::put('teams/1/inventory/notes.jpg', 'not an image');

    expect(app(CreateThumbnail::class)->handle('teams/1/inventory/notes.jpg'))->toBeNull();
});

test('an export holds the organization\'s items and their photos, and nothing of another organization', function () {
    Storage::fake();

    $team = ownerWithModules('inventory')->currentTeam;
    $otherTeam = ownerWithModules('inventory')->currentTeam;

    $bin = Item::factory()->for($team)->bin()->create(['name' => 'Blue bin']);
    $path = Item::storageDirectory($team->id).'/drill.jpg';
    Storage::put($path, 'photo');
    Item::factory()->for($team)->childOf($bin)->create(['name' => 'Drill', 'photo_path' => $path, 'metadata' => ['Brand' => 'Acme']]);
    Item::factory()->for($team)->create(['name' => 'Thrown away'])->delete();
    Item::factory()->for($otherTeam)->create(['name' => 'Theirs']);

    $archive = new ZipArchive;
    $archive->open(Storage::path(app(ExportTeam::class)->handle($team)));
    $items = collect(json_decode((string) $archive->getFromName('inventory/items.json'), true))->keyBy('name');

    expect($items->keys()->all())->toEqualCanonicalizing(['Blue bin', 'Drill', 'Thrown away'])
        ->and($items['Drill'])->toMatchArray(['inside' => $bin->id, 'type' => 'item', 'details' => ['Brand' => 'Acme'], 'photo' => 'inventory/files/drill.jpg'])
        ->and($items['Thrown away']['deleted_at'])->not->toBeNull()
        ->and($archive->getFromName('inventory/files/drill.jpg'))->toBe('photo');
});

test('deleting an organization for good removes its items and photos', function () {
    Storage::fake();

    $team = ownerWithModules('inventory')->currentTeam;
    $path = Item::storageDirectory($team->id).'/drill.jpg';
    Storage::put($path, 'photo');
    Item::factory()->for($team)->create(['photo_path' => $path]);

    app(PurgeTeam::class)->handle($team);

    expect(Item::withTrashed()->count())->toBe(0)
        ->and(Storage::exists($path))->toBeFalse();
});
