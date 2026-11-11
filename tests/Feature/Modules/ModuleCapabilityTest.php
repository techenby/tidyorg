<?php

use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Facades\Modules;
use App\Modules\ModuleException;
use Tests\Fixtures\Modules\People\BrokenPeopleModule;
use Tests\Fixtures\Modules\People\FakePeopleDirectory;
use Tests\Fixtures\Modules\People\PeopleModule;

test('a capability comes from a module enabled for the organization', function () {
    installModules([PeopleModule::class]);

    $team = ownerWithModules('people')->currentTeam;

    expect(Modules::capability(PeopleDirectory::class, $team))->toBeInstanceOf(FakePeopleDirectory::class);
});

test('there is no capability while the module offering it is not enabled for the organization', function () {
    installModules([PeopleModule::class]);

    $team = ownerWithModules()->currentTeam;
    ownerWithModules('people');

    expect(Modules::capability(PeopleDirectory::class, $team))->toBeNull();
});

test('there is no capability when no installed module offers it', function () {
    $team = ownerWithModules()->currentTeam;

    expect(Modules::capability(PeopleDirectory::class, $team))->toBeNull();
});

test('a module cannot offer a capability it does not implement', function () {
    installModules([BrokenPeopleModule::class]);

    $team = ownerWithModules('broken-people')->currentTeam;

    Modules::capability(PeopleDirectory::class, $team);
})->throws(ModuleException::class);
