<?php

it('redirects root to the live Laraclaw app', function () {
    $response = $this->get('/');

    $response->assertRedirect('/laraclaw/live');
});
