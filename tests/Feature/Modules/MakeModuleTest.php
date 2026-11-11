<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = storage_path('framework/testing/modules-'.uniqid());
});

afterEach(function () {
    File::deleteDirectory($this->path);
});

test('make:module scaffolds a module that installs and serves its page', function () {
    $this->artisan('make:module', ['name' => 'Event Planning', '--path' => $this->path])
        ->assertSuccessful();

    $module = $this->path.'/event-planning';

    expect(json_decode(File::get($module.'/composer.json'), true))
        ->name->toBe('tessera/event-planning')
        ->extra->laravel->providers->toBe(['Tessera\\EventPlanning\\EventPlanningModule']);

    expect($module.'/tests/EventPlanningModuleTest.php')->toBeFile()
        ->and($module.'/database/migrations/.gitkeep')->toBeFile();

    require_once $module.'/src/EventPlanningModule.php';

    installModules(['Tessera\\EventPlanning\\EventPlanningModule']);

    $owner = ownerWithModules('event-planning');

    $this->actingAs($owner)
        ->get(route('event-planning.index', ['current_team' => $owner->currentTeam]))
        ->assertOk()
        ->assertSee('Event Planning');

    $this->actingAs(ownerWithModules())
        ->get(route('event-planning.index', ['current_team' => $owner->currentTeam]))
        ->assertForbidden();
});

test('make:module refuses to overwrite an existing module', function () {
    $this->artisan('make:module', ['name' => 'Events', '--path' => $this->path])->assertSuccessful();

    File::put($this->path.'/events/composer.json', 'mine');

    $this->artisan('make:module', ['name' => 'Events', '--path' => $this->path])->assertFailed();

    expect(File::get($this->path.'/events/composer.json'))->toBe('mine');
});

test('make:module refuses a name that cannot be a module key', function (string $name) {
    $this->artisan('make:module', ['name' => $name, '--path' => $this->path])->assertFailed();

    expect(File::exists($this->path))->toBeFalse();
})->with(['123', '!!!']);
