<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Commerce\Console\Commands\ExampleTemplates\ExampleTemplatesCommand;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

beforeEach(function() {
    // Plugin commands are added when Artisan starts, which happened before Commerce booted.
    app(Kernel::class)->registerCommand(app(ExampleTemplatesCommand::class));

    $this->destination = Path::siteTemplates('example-templates-test');
});

afterEach(function() {
    File::deleteDirectory($this->destination);
});

it('won’t replace an existing folder without --overwrite', function() {
    File::ensureDirectoryExists($this->destination);

    artisan('commerce:example-templates', ['--folder-name' => 'example-templates-test'])
        ->assertFailed();

    artisan('commerce:example-templates', ['--folder-name' => 'example-templates-test', '--overwrite' => true])
        ->assertSuccessful();

    expect(File::exists($this->destination . '/index.twig'))->toBeTrue();
});
