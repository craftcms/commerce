<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\transfers;

use craft\web\AssetBundle;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\htmx\HtmxAsset;
use craft\web\View;

/**
 * Edit Transfer edit asset bundle
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0
 */
class TransfersAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
            HtmxAsset::class,
        ];

        $this->css[] = 'css/transfers.css';

        $this->js[] = 'transfers.js';

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);

        if ($view instanceof View) {
            $view->registerTranslations('commerce', [

            ]);
        }
    }
}
