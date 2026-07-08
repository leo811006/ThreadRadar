<?php

use App\Models\User;

it('logs in with valid credentials and starts a session', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();

    $this->assertGuest();
});

it('logs out and invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/logout')
        ->assertNoContent();

    $this->assertGuest();
});

it('returns the current user via /me when authenticated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/me')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id);
});

it('returns 401 from /me when not authenticated', function () {
    $this->getJson('/me')->assertStatus(401);
});
