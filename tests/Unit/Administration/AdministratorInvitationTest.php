<?php

use App\Models\AdministratorInvitation;

test('hashes invitation tokens without storing the raw token', function () {
    $token = str_repeat('a', 64);
    $hash = AdministratorInvitation::hashToken($token);

    expect($hash)
        ->toBe(hash('sha256', $token))
        ->not->toBe($token)
        ->toHaveLength(64);
});
