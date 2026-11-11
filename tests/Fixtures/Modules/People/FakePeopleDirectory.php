<?php

namespace Tests\Fixtures\Modules\People;

use App\Models\Team;
use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Person;
use Illuminate\Support\Collection;

class FakePeopleDirectory implements PeopleDirectory
{
    /**
     * Names keyed by organization id, then by person id.
     *
     * @var array<int, array<int, string>>
     */
    public static array $people = [];

    public static function add(Team $team, int $id, string $name): void
    {
        static::$people[$team->id][$id] = $name;
    }

    public function people(Team $team): Collection
    {
        return collect(static::$people[$team->id] ?? [])
            ->map(fn (string $name, int $id): Person => new Person($id, $name))
            ->sortBy('name')
            ->values();
    }

    public function find(Team $team, array $ids): Collection
    {
        return $this->people($team)
            ->filter(fn (Person $person): bool => in_array($person->id, $ids, true))
            ->keyBy('id');
    }
}
