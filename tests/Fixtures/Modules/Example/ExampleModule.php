<?php

namespace Tests\Fixtures\Modules\Example;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Modules\Module;
use App\Modules\NavigationItem;
use App\Modules\OrganizationExport;
use App\Modules\Widget;
use Illuminate\Support\Facades\DB;

class ExampleModule extends Module
{
    public function key(): string
    {
        return 'example';
    }

    public function name(): string
    {
        return 'Example';
    }

    public function description(): string
    {
        return 'A module used to test the module system.';
    }

    public function path(): string
    {
        return __DIR__;
    }

    public function permissions(): array
    {
        return [
            'example:view' => TeamRole::Member,
            'example:manage' => TeamRole::Admin,
        ];
    }

    public function navigation(): array
    {
        return [
            new NavigationItem('Example things', 'example.index', 'beaker', 'example:view'),
            new NavigationItem('Example admin', 'example.manage', 'wrench', 'example:manage'),
        ];
    }

    public function widgets(): array
    {
        return [
            new Widget('example::widget', 'example:view'),
        ];
    }

    public function exporting(Team $team, OrganizationExport $export): void
    {
        $export->records('things', DB::table('example_things')->where('team_id', $team->id)->get(['name'])->map(fn (object $thing): array => ['name' => $thing->name])->all());
    }

    public function purging(Team $team): void
    {
        DB::table('example_things')->where('team_id', $team->id)->delete();
    }
}
