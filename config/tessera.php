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

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | When Tessera runs behind a reverse proxy or load balancer that handles
    | HTTPS, list the proxy's addresses here, separated by commas, or use
    | "*" to trust whichever proxy the request came through. Without it,
    | links are generated with http:// and visitors' addresses are wrong.
    |
    */

    'trusted_proxies' => env('TESSERA_TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Closed Organizations
    |--------------------------------------------------------------------------
    |
    | When an owner deletes an organization it is closed straight away, and
    | everything stored for it is permanently deleted this many days later.
    | The delay leaves time to recover from a mistake.
    |
    */

    'closed_organization_days' => (int) env('TESSERA_CLOSED_ORGANIZATION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Source Code
    |--------------------------------------------------------------------------
    |
    | Tessera is licensed under the AGPL, which gives everyone who uses an
    | install over a network the right to its source code. The interface
    | links to this address. If you change Tessera, publish your version
    | and point this at it. The revision is the commit that is running.
    |
    */

    'source' => [
        'url' => env('TESSERA_SOURCE_URL'),
        'revision' => env('TESSERA_SOURCE_REVISION'),
    ],

];
