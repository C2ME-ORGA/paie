<?php

test('guests are redirected to the login page when accessing the admin panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('unauthenticated requests to the api are rejected', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

test('unknown routes return a not found response', function () {
    $this->get('/route-inexistante')->assertNotFound();
});
