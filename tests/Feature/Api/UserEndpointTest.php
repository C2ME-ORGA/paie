<?php

use App\Models\User;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RbacSynchronizer::class)->synchronize();
});

test('the user endpoint rejects unauthenticated requests', function () {
    $this->getJson('/api/user')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

test('the user endpoint returns only the authenticated user without sensitive attributes', function () {
    $authenticated = User::factory()->create([
        'name' => 'Alice Authenticated',
        'email' => 'alice@example.test',
    ]);
    $other = User::factory()->create([
        'name' => 'Bob Other',
        'email' => 'bob@example.test',
    ]);

    $token = $authenticated->createToken('api-test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user?user_id='.$other->id);

    $response->assertOk()
        ->assertJsonPath('id', $authenticated->id)
        ->assertJsonPath('name', 'Alice Authenticated')
        ->assertJsonPath('email', 'alice@example.test')
        ->assertJsonMissingPath('password')
        ->assertJsonMissingPath('remember_token')
        ->assertJsonMissing(['email' => 'bob@example.test']);

    expect($response->json('id'))->not->toBe($other->id);
});

test('the user endpoint does not expose roles, permissions or mutation routes', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonMissingPath('roles')
        ->assertJsonMissingPath('permissions');

    $this->postJson('/api/user')->assertStatus(405);
    $this->deleteJson('/api/user')->assertStatus(405);
});
