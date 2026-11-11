<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(function () {
    config(['tidyorg.single_organization' => true]);
});

function invitationFor(string $email, array $attributes = []): TeamInvitation
{
    $owner = User::factory()->create();

    return TeamInvitation::factory()->create([
        'team_id' => $owner->currentTeam->id,
        'email' => $email,
        'role' => TeamRole::Member,
        'invited_by' => $owner->id,
        ...$attributes,
    ]);
}

function registerAs(string $email): TestResponse
{
    return test()->post(route('register.store'), [
        'name' => 'Sam Rivera',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

test('nobody can register without an invitation', function () {
    registerAs('sam@example.org')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::where('email', 'sam@example.org')->exists())->toBeFalse();
});

test('an invited person can register', function () {
    invitationFor('sam@example.org');

    registerAs('Sam@Example.org')->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('an invitation that can no longer be accepted does not open registration', function (array $attributes) {
    invitationFor('sam@example.org', $attributes);

    registerAs('sam@example.org')->assertSessionHasErrors('email');

    $this->assertGuest();
})->with([
    'expired' => [['expires_at' => '2020-01-01 00:00:00']],
    'already accepted' => [['accepted_at' => '2020-01-01 00:00:00']],
]);

test('registration stays open when several organizations are hosted', function () {
    config(['tidyorg.single_organization' => false]);

    registerAs('sam@example.org')->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('the registration page sends visitors without an invitation to sign in', function () {
    $this->get(route('register'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
});

test('the registration page opens from an invitation link', function () {
    $invitation = invitationFor('sam@example.org');

    $this->get(route('register', ['invitation' => $invitation->code]))->assertOk();
});

test('the sign-in page offers registration only from an invitation link', function () {
    $invitation = invitationFor('sam@example.org');

    $this->get(route('login'))->assertOk()->assertDontSee('data-test="register-link"', false);

    $this->get(route('login', ['invitation' => $invitation->code]))->assertOk()->assertSee('data-test="register-link"', false);
});

test('the home page sends guests to sign in and members to their dashboard', function () {
    $this->get(route('home'))->assertRedirect(route('login'));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));
});

test('a second organization cannot be created', function (string $component, string $property) {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test($component)
        ->set($property, 'Second Organization')
        ->call('createTeam')
        ->assertForbidden();

    expect(Team::count())->toBe(1);
})->with([
    'organization settings' => ['pages::teams.index', 'name'],
    'organization switcher' => ['create-team-modal', 'teamName'],
]);

test('someone without an organization cannot create one from onboarding once one exists', function () {
    User::factory()->create();
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk()
        ->assertDontSee('data-test="onboarding-team-name"', false)
        ->assertSee('You need an invitation');

    Livewire::test('pages::onboarding')
        ->set('name', 'Second Organization')
        ->call('createTeam')
        ->assertForbidden();

    expect(Team::count())->toBe(1);
});

test('the first organization can be created from onboarding', function () {
    $user = User::factory()->withoutTeam()->create();

    $this->actingAs($user);

    Livewire::test('pages::onboarding')
        ->set('name', 'Riverside Food Bank')
        ->call('createTeam')
        ->assertHasNoErrors();

    expect(Team::where('name', 'Riverside Food Bank')->exists())->toBeTrue();
});

test('the buttons for a new organization are hidden', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertDontSee('data-test="teams-new-team-button"', false)
        ->assertDontSee('data-test="team-switcher-new-team"', false);
});
