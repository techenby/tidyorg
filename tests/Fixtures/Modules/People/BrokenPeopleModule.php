<?php

namespace Tests\Fixtures\Modules\People;

use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Module;
use stdClass;

class BrokenPeopleModule extends Module
{
    public function key(): string
    {
        return 'broken-people';
    }

    public function name(): string
    {
        return 'Broken people';
    }

    public function path(): string
    {
        return __DIR__;
    }

    public function capabilities(): array
    {
        return [
            PeopleDirectory::class => stdClass::class,
        ];
    }
}
