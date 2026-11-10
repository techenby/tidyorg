<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

test('teams index page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('teams.index'));

    $response->assertOk();
});

test('teams can be created', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('name', 'Test Team')
        ->call('createTeam')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'name' => 'Test Team',
    ]);
});

test('teams cannot be created when the policy denies it', function () {
    $user = User::factory()->create();

    Gate::before(fn ($user, string $ability) => $ability === 'create' ? false : null);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('name', 'Test Team')
        ->call('createTeam')
        ->assertForbidden();

    $this->assertDatabaseMissing('teams', [
        'name' => 'Test Team',
    ]);
});

test('team slug uses next available suffix', function () {
    $user = User::factory()->create();

    Team::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
    Team::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
    Team::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->set('name', 'Acme')
        ->call('createTeam')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'name' => 'Acme',
        'slug' => 'acme-11',
    ]);
});

test('team edit page can be rendered', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($user)
        ->get(route('teams.edit', $team));

    $response->assertOk();
});

test('teams can be updated by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Original Name']);

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('teamName', 'Updated Name')
        ->call('updateTeam')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'name' => 'Updated Name',
    ]);
});

test('teams cannot be updated by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.edit', ['team' => $team])
        ->set('teamName', 'Updated Name')
        ->call('updateTeam')
        ->assertForbidden();
});

test('teams can be deleted by owners', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);
});

test('team deletion requires name confirmation', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', 'Wrong Name')
        ->call('deleteTeam')
        ->assertHasErrors(['deleteName']);

    $this->assertDatabaseHas('teams', [
        'id' => $team->id,
        'deleted_at' => null,
    ]);
});

test('deleting current team switches to alphabetically first remaining team', function () {
    $user = User::factory()->withoutTeam()->create();

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $zuluTeam->id]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $zuluTeam])
        ->set('deleteName', $zuluTeam->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $zuluTeam->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('deleting the only team leaves the user without a current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toBeNull();
});

test('deleting non current team leaves current team unchanged', function () {
    $user = User::factory()->create();
    $currentTeam = $user->currentTeam;
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $user->update(['current_team_id' => $currentTeam->id]);

    $this->actingAs($user);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    $this->assertSoftDeleted('teams', [
        'id' => $team->id,
    ]);

    expect($user->fresh()->current_team_id)->toEqual($currentTeam->id);
});

test('members can leave teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('leaving current team switches to alphabetically first remaining team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->withoutTeam()->create();

    $zuluTeam = Team::factory()->create(['name' => 'Zulu Team']);
    $zuluTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $zuluTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $alphaTeam = Team::factory()->create(['name' => 'Alpha Team']);
    $alphaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $betaTeam = Team::factory()->create(['name' => 'Beta Team']);
    $betaTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $zuluTeam->id]);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $zuluTeam->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToTeam($zuluTeam))->toBeFalse();
    expect($member->fresh()->current_team_id)->toEqual($alphaTeam->id);
});

test('leaving the only team leaves the user without a current team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->withoutTeam()->create();
    $team = $owner->currentTeam;

    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertHasNoErrors();

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
    expect($member->fresh()->current_team_id)->toBeNull();
});

test('team owners cannot leave their team', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('users cannot leave teams they dont belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->call('leaveTeam', $team->id)
        ->assertForbidden();
});

test('leave control is only rendered for leaveable teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $leaveableTeam = Team::factory()->create();

    $leaveableTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $leaveableTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.index')
        ->assertSeeHtml('data-test="team-leave-button"');
});

test('leave control is not rendered for owned teams', function () {
    $user = User::factory()->create();
    $ownedTeam = Team::factory()->create();

    $ownedTeam->members()->attach($user, ['role' => TeamRole::Owner->value]);

    $this->actingAs($user);

    Livewire::test('pages::teams.index')
        ->assertDontSeeHtml('data-test="team-leave-button"');
});

test('deleting team switches other affected users to another of their teams', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $memberOtherTeam = $member->currentTeam;

    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $owner->update(['current_team_id' => $team->id]);
    $member->update(['current_team_id' => $team->id]);

    $this->actingAs($owner);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertHasNoErrors();

    expect($member->fresh()->current_team_id)->toEqual($memberOtherTeam->id);
});

test('teams cannot be deleted by non owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($member);

    Livewire::test('pages::teams.delete-team-modal', ['team' => $team])
        ->set('deleteName', $team->name)
        ->call('deleteTeam')
        ->assertForbidden();
});

test('guests cannot access teams', function () {
    $response = $this->get(route('teams.index'));

    $response->assertRedirect(route('login'));
});
