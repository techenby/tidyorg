<?php

use App\Actions\Teams\ExportTeam;
use App\Enums\TeamRole;
use App\Jobs\ExportTeamData;
use App\Models\User;
use App\Notifications\Teams\TeamExportReady;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();

    $this->owner = User::factory()->create();
    $this->team = $this->owner->currentTeam;

    RateLimiter::clear("team-export:{$this->team->id}");
});

test('an owner can ask for an export, which is prepared on the queue', function () {
    Queue::fake();

    $this->actingAs($this->owner);

    Livewire::test('pages::teams.export', ['team' => $this->team])->call('export')->assertHasNoErrors();

    Queue::assertPushed(ExportTeamData::class, fn (ExportTeamData $job) => $job->team->is($this->team) && $job->requestedBy->is($this->owner));
});

test('only owners see and can use the export', function (TeamRole $role) {
    Queue::fake();

    $user = teammate($this->team, $role);

    $this->actingAs($user)
        ->get(route('teams.edit', ['team' => $this->team->slug]))
        ->assertOk()
        ->assertDontSee('data-test="team-export"', false);

    Livewire::test('pages::teams.export', ['team' => $this->team])->call('export')->assertForbidden();

    Queue::assertNothingPushed();
})->with([
    'admin' => [TeamRole::Admin],
    'member' => [TeamRole::Member],
]);

test('the organization settings page offers the export to its owner', function () {
    $this->actingAs($this->owner)
        ->get(route('teams.edit', ['team' => $this->team->slug]))
        ->assertOk()
        ->assertSee('data-test="team-export"', false);
});

test('exports cannot be requested over and over', function () {
    Queue::fake();

    $this->actingAs($this->owner);

    foreach (range(1, 4) as $attempt) {
        Livewire::test('pages::teams.export', ['team' => $this->team])->call('export');
    }

    Queue::assertPushed(ExportTeamData::class, 3);
});

test('the job builds the export and emails a download link to the person who asked', function () {
    Notification::fake();

    (new ExportTeamData($this->team, $this->owner))->handle(app(ExportTeam::class));

    $files = Storage::files("teams/{$this->team->id}/exports");

    expect($files)->toHaveCount(1);

    Notification::assertSentTo($this->owner, TeamExportReady::class, function (TeamExportReady $notification) use ($files) {
        $mail = $notification->toMail($this->owner);

        return $notification->file === basename($files[0])
            && $mail->actionUrl === route('teams.exports.download', ['team' => $this->team->slug, 'file' => basename($files[0])]);
    });
});

test('an owner can download an export', function () {
    $file = basename(app(ExportTeam::class)->handle($this->team));

    $this->actingAs($this->owner)
        ->get(route('teams.exports.download', ['team' => $this->team->slug, 'file' => $file]))
        ->assertOk()
        ->assertDownload($file);
});

test('nobody else can download an export', function () {
    $file = basename(app(ExportTeam::class)->handle($this->team));
    $url = route('teams.exports.download', ['team' => $this->team->slug, 'file' => $file]);

    $this->get($url)->assertRedirect(route('login'));

    $this->actingAs(teammate($this->team, TeamRole::Admin))->get($url)->assertForbidden();

    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
});

test('an owner cannot download another organization\'s export or reach other files', function () {
    $otherTeam = User::factory()->create()->currentTeam;
    $otherFile = basename(app(ExportTeam::class)->handle($otherTeam));
    Storage::put("teams/{$this->team->id}/inventory/photo.jpg", 'photo');

    $this->actingAs($this->owner);

    $this->get(route('teams.exports.download', ['team' => $this->team->slug, 'file' => $otherFile]))->assertNotFound();
    $this->get(route('teams.exports.download', ['team' => $this->team->slug, 'file' => 'missing.zip']))->assertNotFound();
    $this->get("/settings/organizations/{$this->team->slug}/exports/..%2Finventory%2Fphoto.jpg")->assertNotFound();
});
