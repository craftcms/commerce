<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;

function commerceFacadeClasses(): array
{
    return collect(glob(getcwd() . '/src/Support/Facades/*.php') ?: [])
        ->mapWithKeys(function(string $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);

            return [$name => "CraftCms\\Commerce\\Support\\Facades\\$name"];
        })
        ->sortKeys()
        ->all();
}

function commerceComposerLaravelAliases(): array
{
    $composer = file_get_contents(getcwd() . '/composer.json');

    if ($composer === false) {
        return [];
    }

    return json_decode($composer, true)['extra']['laravel']['aliases'] ?? [];
}

test('every Commerce facade is registered as a Laravel alias', function() {
    $aliases = commerceComposerLaravelAliases();
    $facades = commerceFacadeClasses();

    expect(array_intersect_key($aliases, $facades))->toBe($facades);

    foreach ($aliases as $class) {
        expect(is_subclass_of($class, Facade::class))->toBeTrue();
    }
});
