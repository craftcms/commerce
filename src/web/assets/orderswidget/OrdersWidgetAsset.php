<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\orderswidget;

use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\web\AssetBundle;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\assets\cp\CpAsset;

/**
 * Asset bundle for the Orders widget
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class OrdersWidgetAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
            StatWidgetsAsset::class,
            AdminTableAsset::class
        ];
        $this->js[] = 'js/OrdersWidgetSettings.js';

        parent::init();
    }
}
