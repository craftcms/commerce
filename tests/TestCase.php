<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests;

use Craft;
use CraftCms\Cms\Database\Migrations\Install;
use CraftCms\Cms\Database\Migrator;
use CraftCms\Cms\Site\Data\Site;
use CraftCms\Commerce\Tests\Support\DatabaseLock;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;

use function Orchestra\Testbench\default_skeleton_path;
use function Orchestra\Testbench\package_path;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    use WithWorkbench;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        DatabaseLock::acquire();
    }

    #[Override]
    protected function setUp(): void
    {
        // Testbench's `#[UsesVendor]` attribute symlinks `vendor/` into its
        // temporary skeleton app too late — the skeleton app (and everything
        // resolved during its boot, e.g. Craft's plugin manifest lookup, which
        // reads `vendor/craftcms/plugins.php` relative to `app()->basePath()`)
        // is already created by the time that attribute's `beforeEach()` fires.
        // Create the symlink once, persistently, before the app exists at all.
        $skeletonVendorPath = default_skeleton_path() . '/vendor';
        if (!is_link($skeletonVendorPath) && !is_dir($skeletonVendorPath)) {
            symlink(package_path('vendor'), $skeletonVendorPath);
        }

        parent::setUp();

        config()->set('app.debug', true);

        app()->setLocale('en-US');
        app()->maintenanceMode()->deactivate();

        File::cleanDirectory(config_path('craft/project'));
        File::cleanDirectory(storage_path('runtime/compiled_classes'));
    }

    protected function connectionsToTransact(): array
    {
        if (config('database.default') === 'sqlite') {
            return [config('database.default')];
        }

        return [config('database.default'), 'db2'];
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            Context::forgetHidden('craft.info');
            Context::forgetHidden('craft.isInstalled');

            $this->artisan('db:wipe');

            $site = new Site([
                'name' => 'Craft test site',
                'handle' => 'defaultSite',
                'language' => 'en-US',
                'baseUrl' => 'https://localhost/',
                'primary' => true,
                'hasUrls' => true,
            ]);

            $craftMigration = new Install(
                username: 'craftcms',
                password: 'craftcms2018!!',
                email: 'support@craftcms.com',
                site: $site,
            )->silent();

            Cache::lock(\CraftCms\Cms\ProjectConfig\ProjectConfig::MUTEX_NAME)->forceRelease();

            $migrator = app(Migrator::class)->track('craft');
            $migrator->runMigration($craftMigration, 'up');
            $migrator->getRepository()->log('Install', 1);

            foreach ($migrator->getPendingMigrations() as $file) {
                $migrator->getRepository()->log($migrator->getMigrationName($file), 1);
            }

            // Install Commerce via its Yii2 plugin system
            Craft::$app->plugins->installPlugin('commerce');

            // `Craft::$app->plugins->installPlugin()` is a thin proxy to `CraftCms\Cms\Plugin\Plugins`
            // (the actual, shared plugin manager — there's only one). Its own `installPlugin()` calls
            // `loadPlugins()` as its first line, which runs *before* Commerce has a row in the `plugins`
            // table yet, so it finds nothing to register and sets its internal `pluginsLoaded` flag to
            // `true`. Since `Plugins` is a container singleton, that flag then permanently short-circuits
            // every later `loadPlugins()` call for the rest of the test run, so Commerce's Laravel
            // `register()`/`boot()` (GQL argument handlers, widgets, permissions, CP nav, macros, event
            // listeners, etc.) never fire. Forgetting the singleton forces the next resolution to
            // re-scan the `plugins` table, which now has Commerce's row, and register it correctly.
            app()->forgetInstance(\CraftCms\Cms\Plugin\Plugins::class);
            app(\CraftCms\Cms\Plugin\Plugins::class)->loadPlugins();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        File::cleanDirectory(config_path('craft/project'));
        File::cleanDirectory(storage_path('runtime/compiled_classes'));

        $app->useEnvironmentPath(__DIR__);
        $app->bootstrapWith([LoadEnvironmentVariables::class]);

        tap($app->make(ConfigRepository::class), function(ConfigRepository $config) {
            $config->set('auth.defaults.guard', 'craft');

            $connection = env('DB_CONNECTION', 'testing');
            $driver = $config->get("database.connections.{$connection}.driver");

            $config->set('database.default', $connection);
            $config->set("database.connections.{$connection}.database", env('DB_DATABASE', ':memory:'));
            $config->set("database.connections.{$connection}.host", env('DB_HOST', '127.0.0.1'));
            $config->set("database.connections.{$connection}.username", env('DB_USERNAME', 'root'));
            $config->set("database.connections.{$connection}.password", env('DB_PASSWORD', ''));
            $config->set("database.connections.{$connection}.charset", env('DB_CHARSET', in_array($driver, ['mysql', 'mariadb']) ? 'utf8mb4' : 'utf8'));
            $config->set("database.connections.{$connection}.collation", env('DB_COLLATION', in_array($driver, ['mysql', 'mariadb']) ? 'utf8mb4_unicode_ci' : 'utf8'));
            $config->set("database.connections.{$connection}.prefix", env('DB_PREFIX'));

            DB::setDefaultConnection($connection);
        });
    }
}
