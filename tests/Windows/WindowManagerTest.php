<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Desktop\Facades\Window;

it('can toggle a window into fullscreen', function () {
    Http::fake();

    Window::fullscreen(id: 'main');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://localhost:4000/api/window/fullscreen'
            && $request['id'] === 'main'
            && $request['fullscreen'] === true;
    });
});

it('can toggle a window out of fullscreen', function () {
    Http::fake();

    Window::fullscreen(fullscreen: false, id: 'main');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://localhost:4000/api/window/fullscreen'
            && $request['id'] === 'main'
            && $request['fullscreen'] === false;
    });
});
