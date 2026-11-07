<?php

use App\Models\User;

test('derives the user initials from the full name', function () {
    $user = new User(['name' => 'Jean Pierre Dupont']);

    expect($user->initials())->toBe('JD');
});
