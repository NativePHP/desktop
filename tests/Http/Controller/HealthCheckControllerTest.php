<?php

it('answers a health check', function () {
    $this->withoutMiddleware()
        ->get('_native/api/health')
        ->assertOk()
        ->assertJson(['success' => true]);
});

it('only answers the app', function () {
    config([
        'nativephp-internal.running' => true,
        'nativephp-internal.secret' => 'i-am-secret',
    ]);

    $this->get('_native/api/health')->assertForbidden();

    $this->get('_native/api/health', ['X-NativePHP-Secret' => 'i-am-secret'])->assertOk();
});
