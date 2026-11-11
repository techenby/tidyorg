<?php

use App\Actions\Teams\ExportTeam;
use App\Actions\Teams\PurgeTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

/**
 * Read one JSON file out of an export on the default disk.
 *
 * @return array<int, array<string, mixed>>|null
 */
function exportedRecords(string $path, string $file): ?array
{
    $archive = new ZipArchive;
    $archive->open(Storage::path($path));

    $contents = $archive->getFromName($file);
    $archive->close();

    return $contents === false ? null : json_decode($contents, true);
}

function thingFor(Team $team, string $name): void
{
    DB::table('example_things')->insert(['team_id' => $team->id, 'name' => $name]);
}

test('an export holds the organization, its people, and its pending invitations', function () {
    $owner = User::factory()->create(['name' => 'Jordan Lee', 'email' => 'jordan@example.org']);
    $team = $owner->currentTeam;
    teammate($team, TeamRole::Member);

    TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'sam@example.org', 'role' => TeamRole::Admin, 'invited_by' => $owner->id]);
    TeamInvitation::factory()->create(['team_id' => $team->id, 'email' => 'old@example.org', 'role' => TeamRole::Member, 'invited_by' => $owner->id, 'accepted_at' => now()]);

    $path = app(ExportTeam::class)->handle($team);

    expect($path)->toStartWith("teams/{$team->id}/exports/{$team->slug}-")
        ->and(exportedRecords($path, 'organization.json')[0])->toMatchArray(['name' => $team->name, 'slug' => $team->slug])
        ->and(exportedRecords($path, 'people.json'))->toHaveCount(2)
        ->and(exportedRecords($path, 'people.json'))->toContain(['name' => 'Jordan Lee', 'email' => 'jordan@example.org', 'role' => 'owner'])
        ->and(array_column(exportedRecords($path, 'invitations.json'), 'email'))->toBe(['sam@example.org']);
});

test('an export includes each module\'s records for that organization only', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    $otherTeam = User::factory()->create()->currentTeam;

    thingFor($team, 'Ours');
    thingFor($otherTeam, 'Theirs');

    $path = app(ExportTeam::class)->handle($team);

    expect(exportedRecords($path, 'example/things.json'))->toBe([['name' => 'Ours']]);
});

test('an export includes a module that was switched off after it was used', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    thingFor($team, 'Kept');

    config(['tessera.modules' => []]);

    $path = app(ExportTeam::class)->handle($team);

    expect(exportedRecords($path, 'example/things.json'))->toBe([['name' => 'Kept']]);
});

test('organization:export writes the archive and prints where it is', function () {
    $team = User::factory()->create()->currentTeam;

    $this->artisan('organization:export', ['team' => $team->slug])
        ->expectsOutputToContain("teams/{$team->id}/exports/")
        ->assertSuccessful();

    expect(Storage::files("teams/{$team->id}/exports"))->toHaveCount(1);
});

test('organization:export fails for an unknown organization', function () {
    $this->artisan('organization:export', ['team' => 'missing'])->assertFailed();
});

test('purging deletes the organization, its module data, and its files, and nothing of another organization', function () {
    installModules();

    $owner = User::factory()->create();
    $team = $owner->currentTeam;
    $member = teammate($team);
    $other = User::factory()->create();
    $otherTeam = $other->currentTeam;

    foreach ([$team, $otherTeam] as $each) {
        $each->modules()->create(['module' => 'example']);
        thingFor($each, 'Thing');
        Storage::put("teams/{$each->id}/example/file.txt", 'contents');
        TeamInvitation::factory()->create(['team_id' => $each->id, 'email' => 'sam@example.org', 'role' => TeamRole::Member, 'invited_by' => $owner->id]);
    }

    app(PurgeTeam::class)->handle($team);

    expect(Team::withTrashed()->find($team->id))->toBeNull()
        ->and(DB::table('example_things')->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(DB::table('team_modules')->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(DB::table('team_members')->where('team_id', $team->id)->exists())->toBeFalse()
        ->and(TeamInvitation::where('team_id', $team->id)->exists())->toBeFalse()
        ->and(Storage::exists("teams/{$team->id}/example/file.txt"))->toBeFalse()
        ->and($member->fresh()->current_team_id)->toBeNull()
        ->and(User::find($owner->id))->not->toBeNull();

    expect(Team::find($otherTeam->id))->not->toBeNull()
        ->and(DB::table('example_things')->where('team_id', $otherTeam->id)->exists())->toBeTrue()
        ->and(DB::table('team_modules')->where('team_id', $otherTeam->id)->exists())->toBeTrue()
        ->and(TeamInvitation::where('team_id', $otherTeam->id)->exists())->toBeTrue()
        ->and(Storage::exists("teams/{$otherTeam->id}/example/file.txt"))->toBeTrue()
        ->and($other->fresh()->current_team_id)->toBe($otherTeam->id);
});

test('purging reaches a module that was switched off after it was used', function () {
    installModules();

    $team = User::factory()->create()->currentTeam;
    thingFor($team, 'Thing');

    config(['tessera.modules' => []]);

    app(PurgeTeam::class)->handle($team);

    expect(DB::table('example_things')->count())->toBe(0);
});

test('organization:purge asks before deleting', function () {
    $team = User::factory()->create()->currentTeam;

    $this->artisan('organization:purge', ['team' => $team->slug])
        ->expectsConfirmation("Permanently delete {$team->name} and everything stored for it?", 'no')
        ->assertFailed();

    expect(Team::find($team->id))->not->toBeNull();

    $this->artisan('organization:purge', ['team' => $team->slug])
        ->expectsConfirmation("Permanently delete {$team->name} and everything stored for it?", 'yes')
        ->assertSuccessful();

    expect(Team::withTrashed()->find($team->id))->toBeNull();
});

test('organization:purge reaches an organization that was already closed', function () {
    $team = User::factory()->create()->currentTeam;
    $team->delete();

    $this->artisan('organization:purge', ['team' => $team->slug, '--force' => true])->assertSuccessful();

    expect(Team::withTrashed()->find($team->id))->toBeNull();
});

test('organization:prune purges organizations closed long enough ago and leaves the rest', function () {
    installModules();
    config(['tessera.closed_organization_days' => 30]);

    $open = User::factory()->create()->currentTeam;
    $recentlyClosed = User::factory()->create()->currentTeam;
    $longClosed = User::factory()->create()->currentTeam;

    thingFor($longClosed, 'Thing');

    $recentlyClosed->delete();
    $longClosed->delete();

    $this->travel(29)->days();
    $recentlyClosed->delete();
    $this->travel(2)->days();

    $this->artisan('organization:prune')->assertSuccessful();

    expect(Team::withTrashed()->pluck('id')->all())->toEqualCanonicalizing([$open->id, $recentlyClosed->id])
        ->and(DB::table('example_things')->count())->toBe(0);
});

test('organization:prune deletes exports older than a week', function () {
    $team = User::factory()->create()->currentTeam;

    $old = app(ExportTeam::class)->handle($team);
    touch(Storage::path($old), now()->subDays(8)->getTimestamp());

    $fresh = app(ExportTeam::class)->handle($team);
    Storage::put("teams/{$team->id}/example/keep.txt", 'contents');
    touch(Storage::path("teams/{$team->id}/example/keep.txt"), now()->subDays(8)->getTimestamp());

    $this->artisan('organization:prune')->assertSuccessful();

    expect(Storage::exists($old))->toBeFalse()
        ->and(Storage::exists($fresh))->toBeTrue()
        ->and(Storage::exists("teams/{$team->id}/example/keep.txt"))->toBeTrue();
});

test('exporting and purging skip a module that was never switched on and so has no tables', function () {
    installModules(active: []);

    $team = User::factory()->create()->currentTeam;

    $path = app(ExportTeam::class)->handle($team);

    expect(exportedRecords($path, 'example/things.json'))->toBeNull();

    app(PurgeTeam::class)->handle($team);

    expect(Team::withTrashed()->find($team->id))->toBeNull();
});
