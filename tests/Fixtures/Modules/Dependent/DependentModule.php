<?php

namespace Tests\Fixtures\Modules\Dependent;

use App\Modules\Module;

class DependentModule extends Module
{
    public function key(): string
    {
        return 'dependent';
    }

    public function name(): string
    {
        return 'Dependent';
    }

    public function path(): string
    {
        return __DIR__;
    }

    public function dependencies(): array
    {
        return ['example'];
    }
}
