<?php

namespace Tessera\Members;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Module;
use App\Modules\NavigationItem;
use Illuminate\Support\Facades\Gate;
use Tessera\Members\Models\Member;
use Tessera\Members\Policies\MemberPolicy;

class MembersModule extends Module
{
    public function key(): string
    {
        return 'members';
    }

    public function name(): string
    {
        return 'Members';
    }

    public function description(): string
    {
        return 'Keep a directory of the people your organization works with.';
    }

    public function icon(): string
    {
        return 'users';
    }

    public function path(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Anyone in the organization can look people up; changing their records is for admins.
     */
    public function permissions(): array
    {
        return [
            'members:view' => TeamRole::Member,
            'members:manage' => TeamRole::Admin,
        ];
    }

    public function navigation(): array
    {
        return [
            new NavigationItem('Members', 'members.index', 'users', 'members:view'),
        ];
    }

    public function capabilities(): array
    {
        return [
            PeopleDirectory::class => MemberDirectory::class,
        ];
    }

    public function purging(Team $team): void
    {
        Member::where('team_id', $team->id)->delete();
    }

    protected function bootModule(): void
    {
        Gate::policy(Member::class, MemberPolicy::class);
    }
}
