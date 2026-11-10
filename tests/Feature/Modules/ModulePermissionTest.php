<?php

use App\Enums\TeamRole;
use App\Models\User;
use App\Modules\Facades\Modules;
use App\Modules\Module;
use App\Modules\ModuleException;
use App\Modules\ModuleRegistry;

test('module permissions are granted from the minimum role upward', function (TeamRole $role, bool $canView, bool $canManage) {
    installModules();

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->modules()->create(['module' => 'example']);

    $user = User::factory()->withoutTeam()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    expect($user->hasTeamPermission($team, 'example:view'))->toBe($canView)
        ->and($user->hasTeamPermission($team, 'example:manage'))->toBe($canManage);
})->with([
    'member' => [TeamRole::Member, true, false],
    'admin' => [TeamRole::Admin, true, true],
    'owner' => [TeamRole::Owner, true, true],
]);

test('module permissions are denied to people outside the organization', function () {
    installModules();

    $owner = User::factory()->create();
    $owner->currentTeam->modules()->create(['module' => 'example']);

    $outsider = User::factory()->create();

    expect($outsider->hasTeamPermission($owner->currentTeam, 'example:view'))->toBeFalse();
});

test('module permissions are denied while the module is not enabled for the organization', function () {
    installModules();

    $owner = User::factory()->create();

    expect($owner->hasTeamPermission($owner->currentTeam, 'example:view'))->toBeFalse();
});

test('unknown permissions are denied', function () {
    installModules();

    $owner = User::factory()->create();
    $owner->currentTeam->modules()->create(['module' => 'example']);

    expect($owner->hasTeamPermission($owner->currentTeam, 'example:unknown'))->toBeFalse()
        ->and($owner->hasTeamPermission($owner->currentTeam, 'missing:view'))->toBeFalse();
});

test('navigation entries are limited to what the user is permitted to see', function () {
    installModules();

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $team->modules()->create(['module' => 'example']);

    $member = User::factory()->withoutTeam()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    expect(Modules::navigationFor($team, $member)->pluck('label')->all())->toBe(['Example things'])
        ->and(Modules::navigationFor($team, $owner)->pluck('label')->all())->toBe(['Example things', 'Example admin']);
});

test('a module cannot define a permission outside its own key', function () {
    $module = new class(app()) extends Module
    {
        public function key(): string
        {
            return 'rogue';
        }

        public function name(): string
        {
            return 'Rogue';
        }

        public function path(): string
        {
            return __DIR__;
        }

        public function permissions(): array
        {
            return ['team:delete' => TeamRole::Member];
        }
    };

    app(ModuleRegistry::class)->add($module);
})->throws(ModuleException::class);
