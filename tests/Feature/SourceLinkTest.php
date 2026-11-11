<?php

use App\Models\User;

test('the source code is linked for guests and members when an address is set', function () {
    config(['tidyorg.source' => ['url' => 'https://example.org/tidyorg', 'revision' => '0123456789abcdef0123']]);

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="https://example.org/tidyorg"', false)
        ->assertSee('revision 0123456789ab');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertSee('href="https://example.org/tidyorg"', false);
});

test('no source link is shown when no address is set', function () {
    config(['tidyorg.source' => ['url' => null, 'revision' => null]]);

    $user = User::factory()->create();

    $this->get(route('login'))->assertOk()->assertDontSee('data-test="source-link"', false);

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertOk()
        ->assertDontSee('Source code');
});
