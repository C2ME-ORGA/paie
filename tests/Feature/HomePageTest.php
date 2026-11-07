<?php

test('renders the home page successfully', function () {
    $this->get('/')->assertOk();
});
