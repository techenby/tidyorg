<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Modules\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Modules\Example\ExampleModule;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../modules/*/tests');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Install module service providers the way package discovery would, then migrate their tables.
 *
 * @param  list<class-string<Module>>  $providers
 * @param  string|list<string>  $active
 */
function installModules(array $providers = [ExampleModule::class], string|array $active = '*'): void
{
    config(['tessera.modules' => $active]);

    foreach ($providers as $provider) {
        app()->register($provider);
    }

    Route::getRoutes()->refreshNameLookups();
    Route::getRoutes()->refreshActionLookups();

    Artisan::call('migrate');
}

/**
 * Create a user who owns an organization with the given modules enabled.
 */
function ownerWithModules(string ...$modules): User
{
    $owner = User::factory()->create();

    foreach ($modules as $module) {
        $owner->currentTeam->modules()->create(['module' => $module]);
    }

    return $owner;
}

/**
 * Create a user who belongs only to the given organization, with the given role.
 */
function teammate(Team $team, TeamRole $role = TeamRole::Member): User
{
    $user = User::factory()->withoutTeam()->create();

    $team->members()->attach($user, ['role' => $role->value]);
    $user->switchTeam($team);

    return $user;
}
