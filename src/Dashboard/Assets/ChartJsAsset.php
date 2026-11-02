<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Assets;

use CraftCms\Cms\View\HtmlStack;
use CraftCms\Cms\View\LegacyAssets\LegacyAssetInterface;
use CraftCms\Commerce\Plugin;

/**
 * Chart.js, with Moment.js and its Chart.js date adapter, for the dashboard widgets.
 */
class ChartJsAsset implements LegacyAssetInterface
{
    public array $depends = [];

    public function register(HtmlStack $htmlStack): void
    {
        $plugin = Plugin::getInstance();

        $htmlStack->jsFile($plugin->asset('legacy/chartjs/Chart.bundle.min.js'));
        $htmlStack->jsFile($plugin->asset('legacy/chartjs/moment-with-locales.min.js'));
        $htmlStack->jsFile($plugin->asset('legacy/chartjs/chartjs-adapter-moment.min.js'));
    }
}
