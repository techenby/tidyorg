<?php

namespace Tests\Fixtures\Modules\People;

use App\Modules\Contracts\PeopleDirectory;
use App\Modules\Module;

/**
 * Offers a people directory, so a module that can use one is testable without the Members module.
 */
class PeopleModule extends Module
{
    public function key(): string
    {
        return 'people';
    }

    public function name(): string
    {
        return 'People';
    }

    public function path(): string
    {
        return __DIR__;
    }

    public function capabilities(): array
    {
        return [
            PeopleDirectory::class => FakePeopleDirectory::class,
        ];
    }
}
