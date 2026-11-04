<?php

declare(strict_types=1);

namespace Workbench\App\Console\Commands;

use CraftCms\Cms\Database\Table;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Override;

/**
 * Builds the Workbench app on its first run (or with `--fresh`), then serves it.
 */
final class ServeCommand extends Command
{
    #[Override]
    protected $signature = 'workbench:serve
        {--port= : The port to serve on (defaults to the APP_URL port)}
        {--fresh : Rebuild and reseed the database}';

    #[Override]
    protected $description = 'Build and serve the Commerce Workbench application.';

    public function handle(): int
    {
        if ($this->call('package:sync-skeleton') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->linkCmsAssets();

        if ($this->option('fresh')) {
            if ($this->call('package:drop-sqlite-db') !== self::SUCCESS) {
                return self::FAILURE;
            }

            // Craft won't install over the project config of a previous install.
            File::deleteDirectory(config_path('craft/project'));
        }

        if ($this->call('package:create-sqlite-db') !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            // This process booted against the old database, so reconnect and forget that Craft was installed.
            DB::purge();
            Context::forgetHidden('craft.info');
            Context::forgetHidden('craft.isInstalled');
        }

        if (($this->option('fresh') || !Schema::hasTable(Table::INFO)) && $this->call('workbench:build') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $url = rtrim((string)config('app.url'), '/');
        $port = (int)($this->option('port') ?? parse_url($url, PHP_URL_PORT) ?? 8125);

        $this->components->info("Workbench available at $url/admin");

        return $this->call('serve', [
            '--host' => '127.0.0.1',
            '--port' => $port,
            '--tries' => 1,
        ]);
    }

    /**
     * Serves the CMS assets from a CMS checkout instead of the installed package, for working
     * against a CMS branch whose assets aren't released yet.
     */
    private function linkCmsAssets(): void
    {
        $assetsPath = config('workbench.cms_assets_path');

        if (!$assetsPath) {
            return;
        }

        $link = public_path('vendor/craft');

        if (is_link($link)) {
            File::delete($link);
        } elseif (File::isDirectory($link)) {
            File::deleteDirectory($link);
        }

        File::link($assetsPath, $link);
    }
}
