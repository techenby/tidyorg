<?php

use App\Enums\TeamRole;
use App\Models\TeamInvitation;
use App\Models\User;

beforeEach(function () {
    config(['tidyorg.single_organization' => false, 'tidyorg.registration' => 'invitation']);

    $this->details = [
        'name' => 'Sam Rivera',
        'email' => 'sam@example.org',
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
});

test('nobody can register without an invitation when registration is by invitation', function () {
    $this->post(route('register.store'), $this->details)->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::where('email', 'sam@example.org')->exists())->toBeFalse();
});

test('an invited person can still register', function () {
    $owner = User::factory()->create();

    TeamInvitation::factory()->create([
        'team_id' => $owner->currentTeam->id,
        'email' => 'sam@example.org',
        'role' => TeamRole::Member,
        'invited_by' => $owner->id,
    ]);

    $this->post(route('register.store'), $this->details)->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('the registration page sends visitors without an invitation to sign in', function () {
    $this->get(route('register'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
});

test('neither the sign-in page nor the welcome page offers registration', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('data-test="register-link"', false);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('href="'.route('register').'"', false)
        ->assertSee('href="'.route('login').'"', false);
});

test('anyone can register when registration is open', function () {
    config(['tidyorg.registration' => 'open']);

    $this->post(route('register.store'), $this->details)->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});
