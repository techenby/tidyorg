<?php

use App\Models\Team;
use App\Models\User;
use App\Modules\Facades\Modules;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('module:list reports when no modules are installed', function () {
    app()->instance(ModuleRegistry::class, new ModuleRegistry);

    $this->artisan('module:list')
        ->expectsOutputToContain('No modules are installed.')
        ->assertSuccessful();
});

test('module:list shows installed modules', function () {
    installModules();

    $this->artisan('module:list')
        ->expectsOutputToContain('example')
        ->assertSuccessful();
});

test('module:enable and module:disable switch a module for an organization', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;

    $this->artisan('module:enable', ['key' => 'example', '--team' => $team->slug])->assertSuccessful();

    expect(Modules::enabled('example', $team->fresh()))->toBeTrue();

    $this->artisan('module:disable', ['key' => 'example', '--team' => $team->slug])->assertSuccessful();

    expect(Modules::enabled('example', $team->fresh()))->toBeFalse();
});

test('module:enable fails for an unknown organization or module', function (string $key, string $team) {
    installModules();

    Team::factory()->create(['slug' => 'real-team']);

    $this->artisan('module:enable', ['key' => $key, '--team' => $team])->assertFailed();

    expect(DB::table('team_modules')->count())->toBe(0);
})->with([
    'unknown organization' => ['example', 'missing-team'],
    'unknown module' => ['missing', 'real-team'],
]);

test('module:purge with a team deletes only that organizations data and keeps the tables', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    $otherTeam = User::factory()->create()->currentTeam;

    foreach ([$team, $otherTeam] as $each) {
        $each->modules()->create(['module' => 'example']);
        DB::table('example_things')->insert(['team_id' => $each->id, 'name' => 'Thing']);
    }

    $this->artisan('module:purge', ['key' => 'example', '--team' => $team->slug])
        ->expectsConfirmation("Permanently delete all Example data for {$team->name}?", 'yes')
        ->assertSuccessful();

    expect(DB::table('example_things')->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(DB::table('example_things')->where('team_id', $otherTeam->id)->exists())->toBeTrue()
        ->and(Modules::enabled('example', $team->fresh()))->toBeFalse()
        ->and(Modules::enabled('example', $otherTeam->fresh()))->toBeTrue();
});

test('module:purge does nothing when the confirmation is declined', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    DB::table('example_things')->insert(['team_id' => $team->id, 'name' => 'Thing']);

    $this->artisan('module:purge', ['key' => 'example', '--team' => $team->slug])
        ->expectsConfirmation("Permanently delete all Example data for {$team->name}?", 'no')
        ->assertFailed();

    expect(DB::table('example_things')->where('team_id', $team->id)->exists())->toBeTrue();
});

test('module:purge without a team drops the modules tables and leaves core tables alone', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    $team->modules()->create(['module' => 'example']);

    $this->artisan('module:purge', ['key' => 'example'])
        ->expectsConfirmation('Permanently delete all Example data and drop its tables?', 'yes')
        ->assertSuccessful();

    expect(Schema::hasTable('example_things'))->toBeFalse()
        ->and(Schema::hasTable('teams'))->toBeTrue()
        ->and(Team::count())->toBe(1)
        ->and($team->modules()->count())->toBe(0);
});

test('module:purge without a team refuses when several organizations exist', function () {
    installModules();

    User::factory()->count(2)->create();

    $this->artisan('module:purge', ['key' => 'example'])->assertFailed();

    expect(Schema::hasTable('example_things'))->toBeTrue();
});
