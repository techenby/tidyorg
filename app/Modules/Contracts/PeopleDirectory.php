<?php

namespace App\Modules\Contracts;

use App\Models\Team;
use App\Modules\Person;
use Illuminate\Support\Collection;

/**
 * A directory of the people an organization keeps records of.
 *
 * A module that owns such a directory offers it through Module::capabilities().
 * Other modules ask the registry for it and work without it when no module
 * enabled for the organization provides one.
 */
interface PeopleDirectory
{
    /**
     * Everyone in the organization's directory, ordered by name.
     *
     * @return Collection<int, Person>
     */
    public function people(Team $team): Collection;

    /**
     * The people with the given ids, keyed by id. Ids outside the organization are left out.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Person>
     */
    public function find(Team $team, array $ids): Collection;
}
