<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!Cms::isInstalled()) {
            $status = $this->command->call('craft:install', [
                '--email' => config('workbench.admin_email'),
                '--username' => config('workbench.admin_username'),
                '--password' => config('workbench.admin_password'),
                '--siteName' => 'Craft Commerce Workbench',
                '--siteUrl' => config('app.url'),
                '--language' => 'en-US',
                '--timezone' => 'UTC',
                '--no-interaction' => true,
            ]);

            if ($status !== 0) {
                throw new RuntimeException('Craft installation failed.');
            }

            Context::forgetHidden('craft.info');
            Context::forgetHidden('craft.isInstalled');
        }

        $site = Sites::getPrimarySite();

        if ($site->getBaseUrl(false) !== '$APP_URL') {
            $site->baseUrl = '$APP_URL';

            if (!Sites::saveSite($site)) {
                throw new RuntimeException('Failed to update the Workbench site URL.');
            }
        }

        if (!Plugins::isPluginInstalled('commerce')) {
            Plugins::installPlugin('commerce');
        }

        if (!Plugins::isPluginEnabled('commerce')) {
            Plugins::enablePlugin('commerce');
        }

        Plugins::createPlugin('commerce')->publishFrontendAssets();

        if ($assetsPath = config('workbench.cms_assets_path')) {
            if (!File::isDirectory($assetsPath)) {
                throw new RuntimeException('WORKBENCH_CMS_ASSETS_PATH must point to a built CMS assets resources directory.');
            }

            File::copyDirectory($assetsPath, public_path('vendor/craft'));
        }
    }
}
