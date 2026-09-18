<?php

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Artisan;

test('the module foundation is installed and discovered', function () {
    expect(Artisan::call('module:list'))->toBe(0);

    $composer = json_decode(
        file_get_contents(base_path('composer.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(InstalledVersions::getPrettyVersion('nwidart/laravel-modules'))
        ->toMatch('/^v?13\./')
        ->and($composer['require']['nwidart/laravel-modules'])->toBe('^13.0')
        ->and($composer['config']['allow-plugins']['wikimedia/composer-merge-plugin'])
        ->toBeTrue()
        ->and($composer['extra']['merge-plugin']['include'])
        ->toContain('Modules/*/composer.json');
});
