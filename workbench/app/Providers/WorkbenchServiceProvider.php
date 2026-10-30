<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $files = new Filesystem();
        $root = dirname(__DIR__, levels: 3);
        $composer = json_decode($files->get($root . '/composer.json'), associative: true, flags: JSON_THROW_ON_ERROR);
        $path = $this->app->basePath('vendor/craftcms/plugins.php');
        $plugins = $files->exists($path) ? (require $path) : [];
        $plugins[$composer['name']] = [...$composer['extra'], 'class' => \CraftCms\Commerce\Plugin::class, 'basePath' => $root . '/src'];

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, '<?php return ' . var_export($plugins, return: true) . ';');
    }
}
