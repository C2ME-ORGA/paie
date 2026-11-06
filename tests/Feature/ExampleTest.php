<?php

test('the home page returns a successful response', function () {
    $this->get('/')->assertOk();
});
