<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Active Modules
    |--------------------------------------------------------------------------
    |
    | The modules that are switched on for this deployment. Use "*" to activate
    | every installed module, or a comma-separated list of module keys such as
    | "inventory,members". A module that is installed but not listed here has
    | no routes, navigation, scheduled work, or migrations.
    |
    */

    'modules' => env('TESSERA_MODULES', '*'),

    /*
    |--------------------------------------------------------------------------
    | Single Organization Mode
    |--------------------------------------------------------------------------
    |
    | When this deployment serves one organization, every active module is
    | enabled automatically and the per-organization module switches are
    | hidden. Leave this off when hosting several organizations.
    |
    */

    'single_organization' => (bool) env('TESSERA_SINGLE_ORGANIZATION', false),

];
