<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Dashboard\Assets;

use CraftCms\Cms\View\HtmlStack;
use CraftCms\Cms\View\LegacyAssets\LegacyAssetInterface;
use CraftCms\Commerce\Plugin;

class DeepMergeAsset implements LegacyAssetInterface
{
    public array $depends = [];

    public function register(HtmlStack $htmlStack): void
    {
        $htmlStack->jsFile(Plugin::getInstance()->asset('legacy/deepmerge/umd.js'));
    }
}
