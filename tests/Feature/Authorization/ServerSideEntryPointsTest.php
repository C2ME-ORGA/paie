<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the home page and Filament login page remain public', function () {
    $this->get('/')->assertOk();
    $this->get('/admin/login')->assertOk();
});

test('no HTTP route exposes the administrator bootstrap command', function () {
    $this->get('/batipaie/admin-invite')->assertNotFound();
    $this->post('/batipaie/admin-invite')->assertNotFound();

    $routes = collect(app('router')->getRoutes()->getRoutes());

    expect($routes->contains(
        fn ($route): bool => str_contains($route->uri(), 'admin-invite'),
    ))->toBeFalse();
});

test('the unknown public route returns not found without authentication', function () {
    $this->get('/route-inexistante')->assertNotFound();
});
