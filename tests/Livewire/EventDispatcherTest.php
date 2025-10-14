<?php

it('injects dispatcher into response', function () {

    Route::get('test-inject-in-response', fn () => '<html><head></head></html>');

    $this->get('test-inject-in-response')
        ->assertOk()
        ->assertSee('<!--[NativePHP Livewire Dispatcher]-->', false);
});

it('injects dispatcher into head tag', function () {

    Route::get('test-inject-in-response', fn () => '<html><head></head></html>');

    $expected = <<< 'HTML'
    <html><head>
    <!--[NativePHP Livewire Dispatcher]-->
    HTML;

    $this->get('test-inject-in-response')
        ->assertOk()
        ->assertSee($expected, false);
});

it('injects dispatcher into html body when no head tag is present', function () {

    Route::get('test-inject-in-response', fn () => '<html></html>');

    $expected = <<< 'HTML'
    <html>
    <!--[NativePHP Livewire Dispatcher]-->
    HTML;

    $this->get('test-inject-in-response')
        ->assertOk()
        ->assertSee($expected, false);
});

it('injects dispatcher into the end of the html body when no head tag is present', function () {

    Route::get('test-inject-in-response', fn () => '<html><p>Hello World</p></html>');

    $expected = <<< 'HTML'
    <html><p>Hello World</p>
    <!--[NativePHP Livewire Dispatcher]-->
    HTML;

    $this->get('test-inject-in-response')
        ->assertOk()
        ->assertSee($expected, false);
});

it('doesnt inject dispatcher into responses without a closing html tag', function () {
    Route::get('test-inject-in-response', fn () => 'OK');

    $this->get('test-inject-in-response')
        ->assertOk()
        ->assertDontSee('<!--[NativePHP Livewire Dispatcher]-->', false);
});
