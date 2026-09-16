<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Transfer\Assets;

use CraftCms\Cms\View\HtmlStack;
use CraftCms\Cms\View\LegacyAssets\CpAsset;
use CraftCms\Cms\View\LegacyAssets\HtmxAsset;
use CraftCms\Cms\View\LegacyAssets\LegacyAssetInterface;

class TransfersAsset implements LegacyAssetInterface
{
    public array $depends = [
        CpAsset::class,
        HtmxAsset::class,
    ];

    public function register(HtmlStack $htmlStack): void
    {
        $sourcePath = dirname(__DIR__, 3) . '/src-yii2/web/assets/transfers/dist';

        $htmlStack->cssFile(
            \Craft::$app->getAssetManager()->getPublishedUrl($sourcePath, true, 'css/transfers.css')
        );
        $htmlStack->jsFile(
            \Craft::$app->getAssetManager()->getPublishedUrl($sourcePath, true, 'transfers.js')
        );
    }
}
