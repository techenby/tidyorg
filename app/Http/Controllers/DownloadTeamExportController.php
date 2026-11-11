<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadTeamExportController extends Controller
{
    /**
     * Download an export of the organization's data.
     *
     * The file name comes from the address, so it is limited to the names
     * exports are given and looked for only in this organization's folder.
     */
    public function __invoke(Team $team, string $file): StreamedResponse
    {
        Gate::authorize('export', $team);

        $path = Team::storageDirectory($team->id).'/exports/'.$file;

        abort_unless(preg_match('/^[a-z0-9-]+\.zip$/', $file) === 1 && Storage::exists($path), 404);

        return Storage::download($path);
    }
}
