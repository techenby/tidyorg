<?php

namespace App\Modules;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;

/**
 * Extra sections on the organization settings page, added by modules or by a hosting package.
 *
 * A section is a Blade view that receives the organization as $team.
 */
class OrganizationSettings
{
    /**
     * @var array<string, Closure(User, Team): bool>
     */
    protected array $sections = [];

    /**
     * Add a section, shown to the people the callback allows.
     *
     * @param  Closure(User, Team): bool  $visibleTo
     */
    public function section(string $view, Closure $visibleTo): void
    {
        $this->sections[$view] = $visibleTo;
    }

    /**
     * The views of the sections a user may see for an organization.
     *
     * @return Collection<int, string>
     */
    public function sectionsFor(Team $team, User $user): Collection
    {
        return collect($this->sections)
            ->filter(fn (Closure $visibleTo): bool => $visibleTo($user, $team))
            ->keys();
    }
}
