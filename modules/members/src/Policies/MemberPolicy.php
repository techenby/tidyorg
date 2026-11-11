<?php

namespace TidyOrg\Members\Policies;

use App\Models\Team;
use App\Models\User;
use TidyOrg\Members\Models\Member;

/**
 * Every check goes through the organization's module permissions, which also
 * fail when the user does not belong to it or Members is not enabled for it.
 */
class MemberPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'members:view');
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'members:manage');
    }

    public function update(User $user, Member $member): bool
    {
        return $member->team !== null && $user->hasTeamPermission($member->team, 'members:manage');
    }

    public function delete(User $user, Member $member): bool
    {
        return $member->team !== null && $user->hasTeamPermission($member->team, 'members:manage');
    }
}
