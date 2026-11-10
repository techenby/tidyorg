<?php

arch('core never depends on a module')
    ->expect('App')
    ->not->toUse('Tessera');
