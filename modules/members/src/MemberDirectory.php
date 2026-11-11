<?php

namespace Tessera\Members;

use App\Models\Team;
use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Person;
use Illuminate\Support\Collection;
use Tessera\Members\Models\Member;

/**
 * Offers the organization's members to other modules as a directory of people.
 */
class MemberDirectory implements PeopleDirectory
{
    public function people(Team $team): Collection
    {
        return Member::forTeam($team)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (Member $member): Person => new Person($member->id, $member->name))
            ->values()
            ->toBase();
    }

    public function find(Team $team, array $ids): Collection
    {
        return Member::forTeam($team)
            ->whereIn('id', $ids)
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Member $member): array => [$member->id => new Person($member->id, $member->name)])
            ->toBase();
    }
}
