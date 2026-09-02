<?php

use Native\Desktop\Support\LinuxDevelopmentIdentity;

it('creates a normalized identity scoped to the checkout', function () {
    $first = LinuxDevelopmentIdentity::name('7.example/My App', '/projects/first', 'Linux');
    $second = LinuxDevelopmentIdentity::name('7.example/My App', '/projects/second', 'Linux');

    expect($first)->toMatch('/^_7\.example-My-App\.nativephp-dev-[a-f0-9]{12}$/')
        ->not->toBe($second);
});

it('does not create an identity outside Linux', function () {
    expect(LinuxDevelopmentIdentity::name('com.example.app', '/project', 'Darwin'))->toBeNull()
        ->and(LinuxDevelopmentIdentity::name('com.example.app', '/project', 'Windows'))->toBeNull();
});
