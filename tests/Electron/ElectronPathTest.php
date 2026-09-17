<?php

use Native\Desktop\Drivers\Electron\ElectronServiceProvider;
use Native\Desktop\Support\Composer;
use Symfony\Component\Filesystem\Filesystem;

afterEach(fn () => (new Filesystem)->remove(base_path('nativephp')));

it('resolves sub-paths inside the published electron project', function () {
    (new Filesystem)->dumpFile(base_path('nativephp/electron/package.json'), '{}');

    expect(ElectronServiceProvider::electronPath('node_modules/electron/dist/Electron.app/Contents/Info.plist'))
        ->toBe(base_path('nativephp/electron/node_modules/electron/dist/Electron.app/Contents/Info.plist'));
});

it('falls back to the vendor electron project when none is published', function () {
    expect(ElectronServiceProvider::electronPath('package.json'))
        ->toBe(Composer::desktopPackagePath('resources/electron/package.json'));
});
