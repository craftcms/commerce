<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\console\controllers;

use Craft;
use craft\commerce\console\Controller;
use craft\helpers\ArrayHelper;
use craft\helpers\Console;
use craft\helpers\FileHelper;
use yii\base\ErrorException;
use yii\base\Exception;
use craft\helpers\Html;
use yii\console\ExitCode;

/**
 * Console command to build example templates.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class ExampleTemplatesController extends Controller
{
    /**
     * @inheritdoc
     */
    public $defaultAction = 'generate';

    /**
     * @var ?string Name of the target folder the templates will be copied to.
     * @since 3.3
     */
    public string $folderName = '';

    /**
     * @var bool Whether to overwrite an existing folder. Must be passed if a folder with that name already exists.
     * @since 3.3
     */
    public bool $overwrite = false;

    /**
     * @var ?bool Whether to use htmx
     * @since 3.3
     */
    public ?bool $useHtmx = null;

    /**
     * @var bool Whether to generate and copy to the example-templates build folder (used by Craft Commerce developers)
     * @since 3.3
     */
    public bool $devBuild = false;

    /**
     * @var ?string The base color for the generated example templates.
     * Possible values are: red, yellow, green, blue, indigo, purple, or pink.
     */
    public ?string $baseColor = null;

    /**
     * @var array
     */
    private array $_replacementData = [];

    /**
     * @var string[]
     */
    private array $_colors = ['red', 'yellow', 'green', 'blue', 'indigo', 'purple', 'pink'];

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'folderName';
        $options[] = 'overwrite';
        $options[] = 'baseColor';
        $options[] = 'devBuild';
        return $options;
    }

    /**
     * Generates and copies the example templates.
     *
     * @return int
     * @throws ErrorException
     * @throws Exception
     */
    public function actionGenerate(): int
    {
        if ($this->devBuild) {
            $this->overwrite = true;
            $this->baseColor = 'blue';
            $this->folderName = 'shop';
            $this->useHtmx = true;
        }

        $slash = DIRECTORY_SEPARATOR;
        $pathService = Craft::$app->getPath();
        $templatesPath = $this->_getTemplatesPath();
        $tempDestination = null;

        $exampleTemplatesSource = FileHelper::normalizePath(
            $pathService->getVendorPath() . '/craftcms/commerce/example-templates/src/shop'
        );

        if (!empty($this->folderName)) {
            $folderName = $this->folderName;
        } else {
            $this->stdout('A folder will be copied to your templates directory.' . PHP_EOL);
            $folderName = $this->prompt('Choose folder name:', ['required' => true, 'default' => 'shop']);
        }

        if ($this->useHtmx === null) {
            $this->useHtmx = $this->confirm('Use htmx for forms and links?', true);
        }

        // Folder name is required
        if (!$folderName) {
            $errors[] = 'No destination folder name provided.';
            return $this->_returnErrors($errors);
        }

        // Add the string replacement data to be swapped out in templates
        $this->_replacementData = ArrayHelper::merge($this->_replacementData, [
            '[[folderName]]' => $folderName,
        ]);
        $this->_addCssClassesToReplacementData();
        $this->_addResourceAssetsToReplacementData();

        try {
            // Create a temporary directory to hold the copy of the templates before we replace variables
            $tempDestination = $pathService->getTempPath() . $slash . 'commerce_example_templates_' . md5(uniqid(mt_rand(), true));
            // Copy the templates to the temporary directory
            FileHelper::copyDirectory(
                $exampleTemplatesSource,
                $tempDestination,
                ['recursive' => true, 'copyEmptyDirectories' => true]
            );

            // Find all text files in which we want to replace [[ ]] notation
            $files = FileHelper::findFiles($tempDestination, [
                'only' => ['*.twig', '*.html', '*.svg', '*.css'],
            ]);
            // Set the [[ ]] notation variables and write the files
            foreach ($files as $file) {
                $fileContents = file_get_contents($file);
                $fileContents = str_replace(
                    array_keys($this->_replacementData),
                    array_values($this->_replacementData),
                    $fileContents
                );
                file_put_contents($file, $fileContents);
            }
        } catch (\Exception $e) {
            $errors[] = 'Could not generate templates. Exception raised:';
            $errors[] = $e->getCode() . ' ' . $e->getMessage();
        }

        if (!is_dir($tempDestination)) {
            $errors[] = 'Could not generate templates.';
        }

        if (!empty($errors)) {
            return $this->_returnErrors($errors);
        }

        // New source is our temp directory ready for copying to site templates
        $source = $tempDestination;

        if ($this->devBuild) {
            // If this is a dev build, copy them to the build folder
            $destination = FileHelper::normalizePath(
                Craft::getAlias('@vendor') . '/craftcms/commerce/example-templates/dist/' . $this->folderName
            );
        } else {
            // If this is not a dev build, copy them to the templates folder
            if (!$templatesPath) {
                $errors[] = 'Can not determine the site template path.';
            } else if (!FileHelper::isWritable($templatesPath)) {
                $errors[] = 'Site template path is not writable.';
            }

            if (!empty($errors)) {
                return $this->_returnErrors($errors);
            }

            $destination = $templatesPath . $slash . $folderName;
        }

        $destinationExists = is_dir($destination);

        if ($destinationExists && $this->overwrite) {
            // We’re allowed to overwrite templates, and we’ve got valid source and destination folders
            $this->stdout('Overwriting ...' . PHP_EOL, Console::FG_YELLOW);
            FileHelper::removeDirectory($destination);
        } else if ($destinationExists && !$this->overwrite) {
            // A target folder’s been specified that already exists, but we’re not supposed to overwrite it
            $errors[] = 'Template folder “' . $folderName . '” already exists. Replace it by setting the `overwrite` param to `true`.';
            return $this->_returnErrors($errors);
        }

        // Now let’s try and copy that template directory
        try {
            $this->stdout('Copying ...' . PHP_EOL, Console::FG_YELLOW);
            FileHelper::copyDirectory($source, $destination, ['recursive' => true, 'copyEmptyDirectories' => true]);
        } catch (\Exception $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            return $this->_returnErrors($errors);
        }

        $this->stdout('Done!' . PHP_EOL, Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Adds CSS key-value replacements to the array, where the key is our special `[[ ]]` template notation and
     * the value is what it’ll be replaced with.
     */
    private function _addCssClassesToReplacementData(): void
    {
        $mainColor = $this->baseColor ?: $this->select('Base Tailwind CSS color:', array_combine($this->_colors, $this->_colors));
        $dangerColor = ($mainColor === 'red') ? 'purple' : 'red';
        $this->_replacementData = ArrayHelper::merge($this->_replacementData, [
            '[[color]]' => $mainColor,
            '[[dangerColor]]' => $dangerColor,
            '[[classes.a]]' => "text-$mainColor-500 hover:text-$mainColor-600",
            '[[classes.input]]' => "border border-gray-300 hover:border-gray-500 px-4 py-2 leading-tight rounded",
            '[[classes.box.base]]' => "bg-gray-100 border-$mainColor-300 border-b-2 p-6",
            '[[classes.box.selection]]' => "border-$mainColor-300 border-b-2 px-6 py-4 rounded-md shadow-md hover:shadow-lg",
            '[[classes.box.error]]' => "bg-$dangerColor-100 border-$dangerColor-500 border-b-2 p-6",
            '[[classes.btn.base]]' => "cursor-pointer rounded px-4 py-2 inline-block",
            '[[classes.btn.small]]' => "cursor-pointer rounded px-2 py-1 text-sm inline-block",
            '[[classes.btn.mainColor]]' => "bg-$mainColor-500 hover:bg-$mainColor-600 text-white hover:text-white",
            '[[classes.btn.grayColor]]' => "bg-gray-500 hover:bg-gray-600 text-white hover:text-white",
            '[[classes.btn.grayLightColor]]' => "bg-gray-300 hover:bg-gray-400 text-gray-600 hover:text-white",
        ]);
    }

    /**
     * Adds external resource key-value replacements to the array, where the key is our special `[[ ]]` template
     * notation and the value is what it’ll be replaced with.
     */
    private function _addResourceAssetsToReplacementData(): void
    {
        $resourceTags = [
            Html::cssFile('https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css')
        ];

        if ($this->useHtmx) {
            $resourceTags[] = Html::jsFile('https://unpkg.com/htmx.org@^1');
        }

        $this->_replacementData = ArrayHelper::merge($this->_replacementData, [
            '[[resourceTags]]' => implode("\n", $resourceTags),
            '[[hx-boost]]' => $this->useHtmx ? 'hx-boost="true"' : '',
        ]);
    }

    /**
     * Formats and outputs errors and exits.
     *
     * @param string[] $errors Error strings to be shown to the user
     * @return int
     */
    private function _returnErrors(array $errors): int
    {
        if (count($errors) === 1) {
            $this->stderr('Error: ' . array_shift($errors) . PHP_EOL, Console::FG_RED);
        } else {
            $this->stderr(
                'Errors:' . PHP_EOL . '    - ' . implode(PHP_EOL . '    - ', $errors) . PHP_EOL,
                Console::FG_RED
            );
        }

        return ExitCode::USAGE;
    }

    /**
     * Returns the relevant site base template path.
     *
     * @return string The sites’s base template path
     * @throws Exception
     */
    private function _getTemplatesPath(): string
    {
        $view = Craft::$app->getView();
        $originalMode = $view->getTemplateMode();
        $view->setTemplateMode(\craft\web\View::TEMPLATE_MODE_SITE);
        $templatesPath = $view->getTemplatesPath();
        $view->setTemplateMode($originalMode);
        return $templatesPath;
    }
}
