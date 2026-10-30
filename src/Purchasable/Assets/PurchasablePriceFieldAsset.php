<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Purchasable\Assets;

use CraftCms\Cms\View\HtmlStack;
use CraftCms\Cms\View\LegacyAssets\CpAsset;
use CraftCms\Cms\View\LegacyAssets\HtmxAsset;
use CraftCms\Cms\View\LegacyAssets\LegacyAssetInterface;

class PurchasablePriceFieldAsset implements LegacyAssetInterface
{
    public array $depends = [
        CpAsset::class,
        HtmxAsset::class,
    ];

    public function register(HtmlStack $htmlStack): void
    {
        $sourcePath = dirname(__DIR__, 3) . '/src-yii2/web/assets/purchasablepricefield/dist';

        $htmlStack->jsFile(
            \Craft::$app->getAssetManager()->getPublishedUrl($sourcePath, true, 'purchasablepricefield.js')
        );
    }
}
