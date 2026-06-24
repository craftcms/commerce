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
