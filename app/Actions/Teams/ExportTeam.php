<?php

namespace App\Actions\Teams;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Modules\Module;
use App\Modules\ModuleRegistry;
use App\Modules\OrganizationExport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ExportTeam
{
    public function __construct(private ModuleRegistry $modules)
    {
        //
    }

    /**
     * Write everything stored for an organization to a zip archive on the default disk and return its path.
     *
     * Every module that holds data contributes, whether or not it is switched
     * on, because switching a module off leaves its data in place.
     */
    public function handle(Team $team): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Exporting needs the PHP zip extension.');
        }

        $working = tempnam(sys_get_temp_dir(), 'tidyorg-export-');

        if ($working === false) {
            throw new RuntimeException('The export could not be started because no temporary file could be created.');
        }

        try {
            $archive = new ZipArchive;
            $archive->open($working, ZipArchive::OVERWRITE);

            $export = new OrganizationExport($archive);

            $this->exportOrganization($team, $export);

            $this->modules->holdingData()->each(
                fn (Module $module) => $module->exporting($team, $export->in($module->key())),
            );

            $archive->close();

            $path = Team::storageDirectory($team->id).'/exports/'.$team->slug.'-'.now()->format('Y-m-d-His').'-'.Str::lower(Str::random(8)).'.zip';

            $stream = fopen($working, 'r');

            if ($stream === false || ! Storage::writeStream($path, $stream)) {
                throw new RuntimeException('The export could not be saved.');
            }

            return $path;
        } finally {
            File::delete($working);
        }
    }

    private function exportOrganization(Team $team, OrganizationExport $export): void
    {
        $export->records('organization', [[
            'name' => $team->name,
            'slug' => $team->slug,
            'created_at' => $team->created_at?->toIso8601String(),
            'exported_at' => now()->toIso8601String(),
            'modules' => $this->modules->enabledFor($team)->keys()->all(),
        ]]);

        $export->records('people', $team->members()->orderBy('name')->get()->map(fn (User $user): array => [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->teamRole($team)?->value,
        ])->all());

        $export->records('invitations', $team->invitations()->pending()->get()->map(fn (TeamInvitation $invitation): array => [
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ])->all());
    }
}
