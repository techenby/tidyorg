<?php

namespace App\Modules;

/**
 * Someone an organization keeps a record of, as one module describes them to another.
 */
readonly class Person
{
    public function __construct(
        public int $id,
        public string $name,
    ) {
        //
    }
}
