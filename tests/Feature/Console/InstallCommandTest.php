<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Modules\Facades\Modules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Every run here skips the .env step, which would otherwise edit the real file.
 *
 * @param  array<string, string>  $options
 * @return array<string, string|bool>
 */
function installOptions(array $options = []): array
{
    return ['--skip-env' => true, ...$options];
}

const ADMINISTRATOR = [
    '--name' => 'Jordan Lee',
    '--email' => 'jordan@example.org',
    '--password' => 'a-long-passphrase',
    '--organization' => 'Riverside Food Bank',
];

test('it asks for the first administrator and creates their organization', function () {
    $this->artisan('tidyorg:install', installOptions())
        ->expectsQuestion('Your name', 'Jordan Lee')
        ->expectsQuestion('Your email address', 'jordan@example.org')
        ->expectsQuestion('Choose a password', 'a-long-passphrase')
        ->expectsQuestion('The name of your organization', 'Riverside Food Bank')
        ->expectsOutputToContain('Tidy Org is ready')
        ->assertSuccessful();

    $user = User::where('email', 'jordan@example.org')->firstOrFail();
    $team = Team::where('name', 'Riverside Food Bank')->firstOrFail();

    expect($user->name)->toBe('Jordan Lee')
        ->and(Hash::check('a-long-passphrase', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->teamRole($team))->toBe(TeamRole::Owner)
        ->and($user->current_team_id)->toBe($team->id);
});

test('it takes the administrator from options when run without interaction', function () {
    $this->artisan('tidyorg:install', installOptions([...ADMINISTRATOR, '--no-interaction' => true]))
        ->assertSuccessful();

    expect(User::where('email', 'jordan@example.org')->exists())->toBeTrue()
        ->and(Team::where('name', 'Riverside Food Bank')->exists())->toBeTrue();
});

test('it fails without interaction when an answer is missing', function () {
    $options = ADMINISTRATOR;
    unset($options['--password']);

    $this->artisan('tidyorg:install', installOptions([...$options, '--no-interaction' => true]))
        ->expectsOutputToContain('Pass --name, --email, --password, and --organization')
        ->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Team::count())->toBe(0);
});

test('it rejects answers that are not valid', function (array $override) {
    $this->artisan('tidyorg:install', installOptions([...ADMINISTRATOR, ...$override, '--no-interaction' => true]))
        ->assertFailed();

    expect(User::count())->toBe(0)
        ->and(Team::count())->toBe(0);
})->with([
    'an email address that is not one' => [['--email' => 'jordan']],
    'a reserved organization name' => [['--organization' => 'onboarding']],
    'a password that is too short' => [['--password' => 'short']],
]);

test('it does not create a second administrator when an account exists', function () {
    User::factory()->create();

    $this->artisan('tidyorg:install', installOptions([...ADMINISTRATOR, '--no-interaction' => true]))
        ->expectsOutputToContain('already has an account')
        ->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(User::where('email', 'jordan@example.org')->exists())->toBeFalse();
});

test('it switches the active modules on for the first organization when several are hosted', function () {
    installModules();

    $this->artisan('tidyorg:install', installOptions([...ADMINISTRATOR, '--no-interaction' => true]))
        ->assertSuccessful();

    $team = Team::where('name', 'Riverside Food Bank')->firstOrFail();

    expect(DB::table('team_modules')->where('team_id', $team->id)->pluck('module')->all())->toContain('example')
        ->and(Modules::enabled('example', $team))->toBeTrue();
});

test('it leaves the module switches alone for a single organization', function () {
    installModules();
    config(['tidyorg.single_organization' => true]);

    $this->artisan('tidyorg:install', installOptions([...ADMINISTRATOR, '--no-interaction' => true]))
        ->assertSuccessful();

    expect(DB::table('team_modules')->count())->toBe(0)
        ->and(Modules::enabled('example', Team::firstOrFail()))->toBeTrue();
});

test('it warns when outgoing mail is not set up', function () {
    config(['mail.default' => 'log']);

    $this->artisan('tidyorg:install', installOptions([...ADMINISTRATOR, '--no-interaction' => true]))
        ->expectsOutputToContain('Outgoing mail is not set up')
        ->assertSuccessful();
});
