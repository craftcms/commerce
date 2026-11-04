<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\ExampleTemplates;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\File as FileHelper;
use CraftCms\Commerce\Plugin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Override;
use Throwable;

class ExampleTemplatesCommand extends Command
{
    use CraftCommand;

    #[Override]
    protected $signature = 'commerce:example-templates
        {--folder-name= : The name of the target folder the templates will be copied to.}
        {--overwrite : Whether to overwrite an existing folder.}
        {--base-color=blue : The base color for the generated example templates.}
        {--dev-build : Generate and copy to the example-templates build folder (used by Craft Commerce developers).}
    ';

    #[Override]
    protected $description = 'Generates and copies the example templates.';

    #[Override]
    protected $aliases = ['commerce/example-templates', 'commerce/example-templates/generate'];

    /** @var array<string, string> */
    private array $replacementData = [];

    public function handle(): int
    {
        $devBuild = (bool)$this->option('dev-build');
        $overwrite = (bool)$this->option('overwrite');
        $folderName = $devBuild ? 'shop' : (string)($this->option('folder-name') ?: '');

        if ($devBuild) {
            $overwrite = true;
        }

        $exampleTemplatesPath = dirname(app(Plugin::class)->getBasePath()) . '/example-templates';
        $exampleTemplatesSource = FileHelper::normalizePath($exampleTemplatesPath . '/src/shop');

        if ($folderName === '') {
            $this->line('A folder will be copied to your templates directory.');
            $folderName = (string)$this->ask('Choose folder name', 'shop');
        }

        if ($folderName === '') {
            $this->components->error('No destination folder name provided.');

            return self::FAILURE;
        }

        $this->replacementData = ['[[folderName]]' => $folderName];
        $this->addCssClassesToReplacementData((string)$this->option('base-color'));
        $this->addResourceAssetsToReplacementData();

        $tempDestination = Path::temp('commerce_example_templates_' . Str::random(20));

        try {
            File::copyDirectory($exampleTemplatesSource, $tempDestination);

            $files = collect(File::allFiles($tempDestination))
                ->filter(fn($file) => in_array($file->getExtension(), ['twig', 'html', 'svg', 'css'], true));

            foreach ($files as $file) {
                $contents = str_replace(
                    array_keys($this->replacementData),
                    array_values($this->replacementData),
                    $file->getContents(),
                );
                File::put($file->getPathname(), $contents);
            }
        } catch (Throwable $e) {
            $this->components->error('Could not generate templates: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!is_dir($tempDestination)) {
            $this->components->error('Could not generate templates.');

            return self::FAILURE;
        }

        if ($devBuild) {
            $destination = FileHelper::normalizePath($exampleTemplatesPath . '/dist/' . $folderName);
        } else {
            $templatesPath = Path::siteTemplates();

            if (!$templatesPath) {
                $this->components->error('Can not determine the site template path.');

                return self::FAILURE;
            }

            File::ensureDirectoryExists($templatesPath);

            if (!File::isWritable($templatesPath)) {
                $this->components->error('Site template path is not writable.');

                return self::FAILURE;
            }

            $destination = rtrim($templatesPath, '/\\') . DIRECTORY_SEPARATOR . $folderName;
        }

        $destinationExists = is_dir($destination);

        if ($destinationExists && $overwrite) {
            $this->line('<fg=yellow>Overwriting...</>');
            File::deleteDirectory($destination);
        } elseif ($destinationExists) {
            $this->components->error("The \"$folderName\" directory already exists. Pass --overwrite to replace it.");

            return self::FAILURE;
        }

        try {
            $this->line('<fg=yellow>Copying...</>');
            File::copyDirectory($tempDestination, $destination);
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($tempDestination);
        }

        $this->components->info('Done!');

        return self::SUCCESS;
    }

    /**
     * Adds CSS key-value replacements to the array, where the key is our special `[[ ]]` template notation and
     * the value is what it'll be replaced with.
     */
    private function addCssClassesToReplacementData(string $mainColor): void
    {
        $dangerColor = $mainColor === 'red' ? 'purple' : 'red';

        $this->replacementData = [...$this->replacementData,
            '[[color]]' => $mainColor,
            '[[dangerColor]]' => $dangerColor,
            '[[classes.text.color]]' => "text-$mainColor-600",
            '[[classes.text.dangerColor]]' => "text-$dangerColor-600",
            '[[classes.a]]' => "font-medium text-$mainColor-600 hover:text-$mainColor-500",
            '[[classes.docs]]' => 'text-gray-400 hover:text-gray-600 hover:underline',
            '[[classes.input]]' => "block rounded-md bg-white px-3 py-2 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline-2 focus:-outline-offset-2 focus:outline-$mainColor-600",
            '[[classes.select]]' => "block appearance-none rounded-md bg-white bg-[url(\"data:image/svg+xml,%3Csvg%20xmlns=%27http://www.w3.org/2000/svg%27%20viewBox=%270%200%2020%2020%27%20fill=%27%236b7280%27%3E%3Cpath%20fill-rule=%27evenodd%27%20d=%27M5.22%208.22a.75.75%200%200%201%201.06%200L10%2011.94l3.72-3.72a.75.75%200%201%201%201.06%201.06l-4.25%204.25a.75.75%200%200%201-1.06%200L5.22%209.28a.75.75%200%200%201%200-1.06Z%27%20clip-rule=%27evenodd%27/%3E%3C/svg%3E\")] bg-size-[1.25rem] bg-position-[right_0.5rem_center] bg-no-repeat py-2 pr-9 pl-3 text-sm text-gray-900 outline-1 -outline-offset-1 outline-gray-300 focus:outline-2 focus:-outline-offset-2 focus:outline-$mainColor-600",
            '[[classes.box.base]]' => 'rounded-lg border border-gray-200 bg-white p-6 shadow-xs',
            '[[classes.box.selection]]' => "block cursor-pointer rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-xs hover:border-gray-300 has-checked:border-$mainColor-600 has-checked:ring-1 has-checked:ring-$mainColor-600",
            '[[classes.box.error]]' => "rounded-lg border border-$dangerColor-200 bg-$dangerColor-50 p-6 text-$dangerColor-800",
            '[[classes.btn.base]]' => 'inline-flex cursor-pointer items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold shadow-xs transition focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
            '[[classes.btn.small]]' => 'inline-flex cursor-pointer items-center justify-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-semibold shadow-xs transition',
            '[[classes.btn.mainColor]]' => "bg-$mainColor-600 text-white hover:bg-$mainColor-500 focus-visible:outline-$mainColor-600",
            '[[classes.btn.blue]]' => "bg-$mainColor-600 text-white hover:bg-$mainColor-500 focus-visible:outline-$mainColor-600",
            '[[classes.btn.grayColor]]' => 'bg-white text-gray-900 ring-1 ring-gray-300 ring-inset hover:bg-gray-50',
            '[[classes.btn.grayLightColor]]' => 'bg-gray-100 text-gray-700 hover:bg-gray-200',
            // Styles the markup a gateway outputs for its payment form, which the templates can't add classes to.
            '[[classes.gatewayForm]]' => "space-y-4 [&_craft-input]:block [&_fieldset]:space-y-1 [&_legend]:mb-1 [&_legend]:text-sm [&_legend]:font-medium [&_legend]:text-gray-700 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:block [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:w-full [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:rounded-md [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:bg-white [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:px-3 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:py-2 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:text-sm [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:outline-1 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:-outline-offset-1 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:outline-gray-300 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:focus:outline-2 [&_input:not([type=checkbox]):not([type=radio]):not([type=hidden])]:focus:outline-$mainColor-600",
        ];
    }

    /**
     * Adds external resource key-value replacements to the array, where the key is our special `[[ ]]` template
     * notation and the value is what it'll be replaced with.
     */
    private function addResourceAssetsToReplacementData(): void
    {
        // Tailwind's browser build compiles the classes used on the page, so the templates need no build step.
        $this->replacementData['[[resourceTags]]'] = '<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>';
    }
}
