<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Console\Commands\ExampleTemplates;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\File as FileHelper;
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

        $exampleTemplatesSource = FileHelper::normalizePath(Path::vendor('craftcms/commerce/example-templates/src/shop'));

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
            $destination = FileHelper::normalizePath(Path::vendor('craftcms/commerce/example-templates/dist/' . $folderName));
        } else {
            $templatesPath = Path::siteTemplates();

            if (!$templatesPath) {
                $this->components->error('Can not determine the site template path.');

                return self::FAILURE;
            }

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
            '[[classes.text.color]]' => "text-$mainColor-500",
            '[[classes.text.dangerColor]]' => "text-$dangerColor-500",
            '[[classes.a]]' => "text-$mainColor-500 hover:text-$mainColor-600",
            '[[classes.docs]]' => 'text-gray-400 hover:text-gray-600 hover:underline',
            '[[classes.input]]' => 'border border-gray-300 hover:border-gray-500 px-4 py-2 leading-tight rounded',
            '[[classes.box.base]]' => "bg-gray-100 border-$mainColor-300 border-b-2 p-6",
            '[[classes.box.selection]]' => "border-$mainColor-300 border-b-2 px-6 py-4 rounded-md shadow-md hover:shadow-lg",
            '[[classes.box.error]]' => "bg-$dangerColor-100 border-$dangerColor-500 border-b-2 p-6",
            '[[classes.btn.base]]' => 'cursor-pointer rounded px-4 py-2 inline-block',
            '[[classes.btn.small]]' => 'cursor-pointer rounded px-2 py-1 text-sm inline-block',
            '[[classes.btn.mainColor]]' => "bg-$mainColor-500 hover:bg-$mainColor-600 text-white hover:text-white",
            '[[classes.btn.grayColor]]' => 'bg-gray-500 hover:bg-gray-600 text-white hover:text-white',
            '[[classes.btn.grayLightColor]]' => 'bg-gray-300 hover:bg-gray-400 text-gray-600 hover:text-white',
        ];
    }

    /**
     * Adds external resource key-value replacements to the array, where the key is our special `[[ ]]` template
     * notation and the value is what it'll be replaced with.
     */
    private function addResourceAssetsToReplacementData(): void
    {
        $this->replacementData['[[resourceTags]]'] = '<link rel="stylesheet" href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css">';
    }
}
