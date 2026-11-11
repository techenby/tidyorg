<?php

use App\Actions\Teams\ExportTeam;
use App\Enums\TeamRole;
use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Facades\Modules;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tessera\Members\Models\Member;

beforeEach(function () {
    $this->owner = ownerWithModules('members');
    $this->team = $this->owner->currentTeam;
});

test('the members page lists the organization\'s members', function () {
    Member::factory()->for($this->team)->create(['name' => 'Ada Okafor']);

    $this->actingAs($this->owner)
        ->get(route('members.index', ['current_team' => $this->team]))
        ->assertOk()
        ->assertSee('Ada Okafor');
});

test('guests are sent to the login page', function () {
    $this->get(route('members.index', ['current_team' => $this->team]))
        ->assertRedirect(route('login'));
});

test('the members page does not exist while the module is disabled for the organization', function () {
    $this->team->modules()->delete();

    $this->actingAs($this->owner)
        ->get(route('members.index', ['current_team' => $this->team]))
        ->assertNotFound();
});

test('members appear in the navigation only once enabled', function () {
    $without = ownerWithModules();

    $this->actingAs($this->owner)
        ->get(route('dashboard', ['current_team' => $this->team]))
        ->assertSee(route('members.index', ['current_team' => $this->team]));

    $this->actingAs($without)
        ->get(route('dashboard', ['current_team' => $without->currentTeam]))
        ->assertDontSee(route('members.index', ['current_team' => $without->currentTeam]));
});

test('members can be searched by name or email, whatever the letter case', function () {
    Member::factory()->for($this->team)->create(['name' => 'Ada Okafor', 'email' => 'ada@example.org']);
    Member::factory()->for($this->team)->create(['name' => 'Ben Ruiz', 'email' => 'ben@example.org']);

    Livewire::actingAs($this->owner)
        ->test('members::index')
        ->set('search', 'ADA')
        ->assertSee('Ada Okafor')
        ->assertDontSee('Ben Ruiz')
        ->set('search', 'ben@')
        ->assertSee('Ben Ruiz')
        ->assertDontSee('Ada Okafor');
});

test('an admin can add a member', function () {
    Livewire::actingAs(teammate($this->team, TeamRole::Admin))
        ->test('members::index')
        ->call('create')
        ->set('form.name', 'Ada Okafor')
        ->set('form.email', 'ada@example.org')
        ->set('form.joined_on', '2024-03-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(Member::sole())
        ->team_id->toBe($this->team->id)
        ->name->toBe('Ada Okafor')
        ->email->toBe('ada@example.org')
        ->phone->toBeNull()
        ->and(Member::sole()->joined_on->toDateString())->toBe('2024-03-01');
});

test('a member needs a name, a valid email, and a join date that is not in the future', function () {
    Livewire::actingAs($this->owner)
        ->test('members::index')
        ->call('create')
        ->set('form.name', '')
        ->set('form.email', 'not-an-email')
        ->set('form.joined_on', now()->addDay()->toDateString())
        ->call('save')
        ->assertHasErrors(['form.name', 'form.email', 'form.joined_on']);

    expect(Member::count())->toBe(0);
});

test('a member can be edited and removed', function () {
    $member = Member::factory()->for($this->team)->create(['name' => 'Ada Okafr']);

    $component = Livewire::actingAs($this->owner)->test('members::index');

    $component->call('edit', $member->id)
        ->assertSet('form.name', 'Ada Okafr')
        ->set('form.name', 'Ada Okafor')
        ->set('form.phone', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($member->fresh())
        ->name->toBe('Ada Okafor')
        ->phone->toBeNull();

    $component->call('delete', $member->id);

    expect(Member::count())->toBe(0);
});

test('people with the member role can look but not change anything', function () {
    $member = Member::factory()->for($this->team)->create(['name' => 'Ada Okafor']);

    $viewer = teammate($this->team);

    Livewire::actingAs($viewer)->test('members::index')->assertSee('Ada Okafor');

    Livewire::actingAs($viewer)->test('members::index')->call('create')->assertForbidden();
    Livewire::actingAs($viewer)->test('members::index')->call('edit', $member->id)->assertForbidden();
    Livewire::actingAs($viewer)->test('members::index')->call('delete', $member->id)->assertForbidden();
    Livewire::actingAs($viewer)->test('members::index')->set('form.name', 'Mallory')->call('save')->assertForbidden();

    expect(Member::pluck('name')->all())->toBe(['Ada Okafor']);
});

test('another organization\'s members cannot be seen or changed', function () {
    $theirs = ownerWithModules('members')->currentTeam;
    $theirMember = Member::factory()->for($theirs)->create(['name' => 'Their Member']);

    $this->actingAs($this->owner)
        ->get(route('members.index', ['current_team' => $theirs]))
        ->assertForbidden();

    Livewire::actingAs($this->owner)
        ->test('members::index')
        ->assertDontSee('Their Member')
        ->set('search', 'their')
        ->assertDontSee('Their Member');

    Livewire::actingAs($this->owner)->test('members::index')->call('edit', $theirMember->id)->assertNotFound();
    Livewire::actingAs($this->owner)->test('members::index')->call('delete', $theirMember->id)->assertNotFound();

    expect($theirMember->fresh())->not->toBeNull()
        ->and($this->owner->can('update', $theirMember))->toBeFalse()
        ->and($this->owner->can('delete', $theirMember))->toBeFalse();
});

test('the people directory offers only the organization\'s members, by name', function () {
    $ben = Member::factory()->for($this->team)->create(['name' => 'Ben Ruiz']);
    $ada = Member::factory()->for($this->team)->create(['name' => 'Ada Okafor']);
    $theirs = Member::factory()->create();

    $directory = Modules::capability(PeopleDirectory::class, $this->team);

    expect($directory->people($this->team)->pluck('name')->all())->toBe(['Ada Okafor', 'Ben Ruiz'])
        ->and($directory->find($this->team, [$ben->id, $theirs->id])->keys()->all())->toBe([$ben->id])
        ->and($directory->find($this->team, [$ada->id])->first()->name)->toBe('Ada Okafor');
});

test('purging an organization removes its members and leaves other organizations alone', function () {
    Member::factory()->for($this->team)->count(2)->create();
    $kept = Member::factory()->create();

    $this->artisan('module:purge', ['key' => 'members', '--team' => $this->team->slug, '--force' => true])
        ->assertSuccessful();

    expect(Member::pluck('id')->all())->toBe([$kept->id]);
});

test('an export holds the organization\'s members and nobody else\'s', function () {
    Storage::fake();

    Member::factory()->for($this->team)->create(['name' => 'Ada Okafor', 'email' => 'ada@example.org']);
    Member::factory()->for(ownerWithModules('members')->currentTeam)->create(['name' => 'Someone Else']);

    $archive = new ZipArchive;
    $archive->open(Storage::path(app(ExportTeam::class)->handle($this->team)));
    $members = json_decode((string) $archive->getFromName('members/members.json'), true);

    expect($members)->toHaveCount(1)
        ->and($members[0])->toMatchArray(['name' => 'Ada Okafor', 'email' => 'ada@example.org']);
});
