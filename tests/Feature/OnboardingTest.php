<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('onboarding'))->assertRedirect(route('login'));
});

test('users without a team can view onboarding', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSeeHtml('data-test="onboarding-team-name"');
});

test('users with a team are redirected from onboarding to their dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('users whose current team was cleared are switched to a remaining team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $user->update(['current_team_id' => null]);

    $this->actingAs($user->fresh())
        ->get(route('onboarding'))
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));

    expect($user->fresh()->current_team_id)->toEqual($team->id);
});

test('users without a team can create their first organization', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user);

    Livewire::test('pages::onboarding')
        ->set('name', 'Riverside Food Bank')
        ->call('createTeam')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', ['current_team' => 'riverside-food-bank']));

    $team = Team::where('slug', 'riverside-food-bank')->firstOrFail();

    expect($user->fresh()->current_team_id)->toEqual($team->id);
    expect($user->fresh()->teamRole($team))->toBe(TeamRole::Owner);
});

test('an organization cannot be created with an invalid name', function (string $name) {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user);

    Livewire::test('pages::onboarding')
        ->set('name', $name)
        ->call('createTeam')
        ->assertHasErrors('name');

    expect($user->fresh()->teams)->toBeEmpty();
})->with([
    'empty' => [''],
    'reserved' => ['onboarding'],
]);

test('pending invitations are offered during onboarding', function () {
    $owner = User::factory()->create();
    $user = User::factory()->withoutTeam()->create();

    TeamInvitation::factory()->create([
        'team_id' => $owner->currentTeam->id,
        'email' => $user->email,
        'role' => TeamRole::Member,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertSeeHtml('data-test="pending-invitation-row"');
});

test('users without a team are sent to onboarding after logging in', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('onboarding', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('users without a team can still reach their account settings', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(route('onboarding'));
});

test('the home page links users without a team to onboarding', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('onboarding'));
});
