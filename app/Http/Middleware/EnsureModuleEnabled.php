<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private ModuleRegistry $modules)
    {
        //
    }

    /**
     * Respond with a 404 unless the module is enabled for the request's organization.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (is_string($team)) {
            $team = Team::where('slug', $team)->first();
        }

        abort_unless($team instanceof Team && $this->modules->enabled($module, $team), 404);

        return $next($request);
    }
}
