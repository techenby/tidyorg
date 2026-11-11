<?php

namespace Tessera\Inventory\Policies;

use App\Models\Team;
use App\Models\User;
use Tessera\Inventory\Models\Item;

/**
 * Every check goes through the organization's module permissions, which also
 * fail when the user is not a member or Inventory is not enabled for it.
 */
class ItemPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'inventory:view');
    }

    public function view(User $user, Item $item): bool
    {
        return $this->allows($user, $item, 'inventory:view');
    }

    public function create(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'inventory:manage');
    }

    public function update(User $user, Item $item): bool
    {
        return $this->allows($user, $item, 'inventory:manage');
    }

    public function restore(User $user, Item $item): bool
    {
        return $this->allows($user, $item, 'inventory:manage');
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->allows($user, $item, 'inventory:delete');
    }

    public function forceDelete(User $user, Item $item): bool
    {
        return $this->allows($user, $item, 'inventory:delete');
    }

    /**
     * Moving an item needs permission to manage inventory in both organizations.
     */
    public function move(User $user, Item $item, Team $destination): bool
    {
        return $this->allows($user, $item, 'inventory:manage')
            && $user->hasTeamPermission($destination, 'inventory:manage');
    }

    private function allows(User $user, Item $item, string $permission): bool
    {
        return $item->team !== null && $user->hasTeamPermission($item->team, $permission);
    }
}
