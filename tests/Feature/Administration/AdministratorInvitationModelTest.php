<?php

use App\Models\AdministratorInvitation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('is usable only before expiry, consumption and revocation', function () {
    $now = CarbonImmutable::parse('2026-11-08 10:00:00');

    $valid = new AdministratorInvitation([
        'expires_at' => $now->addHour(),
        'consumed_at' => null,
        'revoked_at' => null,
    ]);

    $expired = new AdministratorInvitation([
        'expires_at' => $now->subMinute(),
        'consumed_at' => null,
        'revoked_at' => null,
    ]);

    $consumed = new AdministratorInvitation([
        'expires_at' => $now->addHour(),
        'consumed_at' => $now,
        'revoked_at' => null,
    ]);

    $revoked = new AdministratorInvitation([
        'expires_at' => $now->addHour(),
        'consumed_at' => null,
        'revoked_at' => $now,
    ]);

    expect($valid->isUsableAt($now))->toBeTrue()
        ->and($valid->isUsableAt($now->addHours(2)))->toBeFalse()
        ->and($expired->isUsableAt($now))->toBeFalse()
        ->and($consumed->isUsableAt($now))->toBeFalse()
        ->and($revoked->isUsableAt($now))->toBeFalse();
});
