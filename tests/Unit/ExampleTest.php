<?php

use App\Models\User;

test('the user initials are derived from the full name', function () {
    $user = new User(['name' => 'Jean Pierre Dupont']);

    expect($user->initials())->toBe('JD');
});
