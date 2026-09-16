<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Inventory\Assets;

use CraftCms\Cms\View\HtmlStack;
use CraftCms\Cms\View\LegacyAssets\AdminTableAsset;
use CraftCms\Cms\View\LegacyAssets\CpAsset;
use CraftCms\Cms\View\LegacyAssets\HtmxAsset;
use CraftCms\Cms\View\LegacyAssets\LegacyAssetInterface;

class InventoryAsset implements LegacyAssetInterface
{
    public array $depends = [
        CpAsset::class,
        HtmxAsset::class,
        AdminTableAsset::class,
    ];

    public function register(HtmlStack $htmlStack): void
    {
        $sourcePath = dirname(__DIR__, 3) . '/src-yii2/web/assets/inventory/dist';

        $htmlStack->cssFile(
            \Craft::$app->getAssetManager()->getPublishedUrl($sourcePath, true, 'css/inventory.css')
        );
        $htmlStack->jsFile(
            \Craft::$app->getAssetManager()->getPublishedUrl($sourcePath, true, 'inventory.js')
        );
    }
}
