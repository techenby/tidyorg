<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;

afterEach(function () {
    TrustProxies::flushState();
});

function bootWithTrustedProxies(?string $proxies): void
{
    config(['tessera.trusted_proxies' => $proxies]);

    (new AppServiceProvider(app()))->boot();
}

test('links use https when a trusted proxy says the visitor used it', function (string $proxies) {
    bootWithTrustedProxies($proxies);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->get(route('login'), ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('action="https://', false);
})->with([
    'any proxy' => ['*'],
    'a listed proxy' => ['10.0.0.9, 10.0.0.5'],
]);

test('a forwarded scheme is ignored when it does not come from a trusted proxy', function (?string $proxies) {
    bootWithTrustedProxies($proxies);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->get(route('login'), ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertDontSee('action="https://', false);
})->with([
    'no proxies configured' => [null],
    'an empty setting' => [''],
    'a different proxy' => ['10.0.0.9'],
]);
