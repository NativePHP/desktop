<?php

use Native\Desktop\Support\LinuxDevelopmentDesktop;

it('creates a normalized identity scoped to the checkout', function () {
    $first = LinuxDevelopmentDesktop::name('7.example/My App', '/projects/first', 'Linux');
    $second = LinuxDevelopmentDesktop::name('7.example/My App', '/projects/second', 'Linux');

    expect($first)->toMatch('/^_7\.example-My-App\.nativephp-dev-[a-f0-9]{12}$/')
        ->not->toBe($second);
});

it('does not create an identity outside Linux', function () {
    expect(LinuxDevelopmentDesktop::name('com.example.app', '/project', 'Darwin'))->toBeNull()
        ->and(LinuxDevelopmentDesktop::name('com.example.app', '/project', 'Windows'))->toBeNull();
});
