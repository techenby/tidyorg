<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Modules\Facades\Modules;
use App\Modules\OrganizationSettings;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Fixtures\Modules\Dependent\DependentModule;
use Tests\Fixtures\Modules\Example\ExampleModule;

test('organization settings list the active modules', function () {
    installModules();

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('teams.edit', ['team' => $owner->currentTeam->slug]))
        ->assertOk()
        ->assertSeeHtml('data-test="module-toggle-example"');
});

test('organization settings have no modules section when none are active', function () {
    config(['tessera.modules' => []]);

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('teams.edit', ['team' => $owner->currentTeam->slug]))
        ->assertOk()
        ->assertDontSeeHtml('data-test="team-modules"');
});

test('organization settings have no modules section in single organization mode', function () {
    installModules();
    config(['tessera.single_organization' => true]);

    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->get(route('teams.edit', ['team' => $owner->currentTeam->slug]))
        ->assertOk()
        ->assertDontSeeHtml('data-test="team-modules"');
});

test('owners and admins can turn a module on and off', function (TeamRole $role) {
    installModules();

    $team = User::factory()->create()->currentTeam;
    $user = User::factory()->withoutTeam()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.modules', ['team' => $team])
        ->call('toggle', 'example')
        ->assertRedirect(route('teams.edit', ['team' => $team->slug]));

    expect(Modules::enabled('example', $team->fresh()))->toBeTrue();

    Livewire::test('pages::teams.modules', ['team' => $team->fresh()])
        ->call('toggle', 'example');

    expect(Modules::enabled('example', $team->fresh()))->toBeFalse();
})->with([
    'owner' => [TeamRole::Owner],
    'admin' => [TeamRole::Admin],
]);

test('members cannot change modules', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    $member = User::factory()->withoutTeam()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.modules', ['team' => $team])
        ->call('toggle', 'example')
        ->assertForbidden();

    expect(Modules::enabled('example', $team->fresh()))->toBeFalse();
});

test('people outside the organization cannot change its modules', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::teams.modules', ['team' => $team])
        ->call('toggle', 'example')
        ->assertForbidden();

    expect(Modules::enabled('example', $team->fresh()))->toBeFalse();
});

test('turning on a module with an unmet dependency changes nothing', function () {
    installModules([ExampleModule::class, DependentModule::class]);

    $owner = User::factory()->create();

    $this->actingAs($owner);

    Livewire::test('pages::teams.modules', ['team' => $owner->currentTeam])
        ->call('toggle', 'dependent')
        ->assertNoRedirect();

    expect(Modules::enabled('dependent', $owner->currentTeam->fresh()))->toBeFalse();
});

test('a section added to the organization settings page is shown only to the people it allows', function () {
    View::addNamespace('fixtures', base_path('tests/Fixtures/views'));

    app(OrganizationSettings::class)->section(
        'fixtures::settings-section',
        fn (User $user, Team $team): bool => $user->ownsTeam($team),
    );

    $owner = User::factory()->create();
    $team = $owner->currentTeam;

    $this->actingAs($owner)
        ->get(route('teams.edit', ['team' => $team->slug]))
        ->assertOk()
        ->assertSee("Extra settings for {$team->name}");

    $this->actingAs(teammate($team, TeamRole::Admin))
        ->get(route('teams.edit', ['team' => $team->slug]))
        ->assertOk()
        ->assertDontSee('data-test="fixture-settings-section"', false);
});
