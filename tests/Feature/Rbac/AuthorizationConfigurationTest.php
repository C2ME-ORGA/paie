<?php

use AlizHarb\ActivityLog\Resources\ActivityLogs\ActivityLogResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

uses(RefreshDatabase::class);

test('registers the Spatie middleware aliases', function () {
    $middleware = app('router')->getMiddleware();

    expect($middleware['role'] ?? null)->toBe(RoleMiddleware::class)
        ->and($middleware['permission'] ?? null)->toBe(PermissionMiddleware::class)
        ->and($middleware['role_or_permission'] ?? null)->toBe(RoleOrPermissionMiddleware::class);
});

test('enables strict authorization on the web-guarded admin panel', function () {
    $panel = Filament::getDefaultPanel();

    expect($panel->isAuthorizationStrict())->toBeTrue()
        ->and($panel->getAuthGuard())->toBe('web')
        ->and($panel->getResources())->toBe([ActivityLogResource::class]);

    foreach ($panel->getResources() as $resource) {
        expect(Gate::getPolicyFor($resource::getModel()))->not->toBeNull();
    }
});

test('keeps wildcard permissions disabled and the Spatie gate check enabled', function () {
    expect(config('permission.enable_wildcard_permission'))->toBeFalse()
        ->and(config('permission.register_permission_check_method'))->toBeTrue();
});
