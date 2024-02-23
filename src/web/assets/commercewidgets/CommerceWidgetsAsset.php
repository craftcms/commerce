<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\commercewidgets;

use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\web\AssetBundle;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\garnish\GarnishAsset;

/**
 * Commerce Widgets functionality for the control panel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
class CommerceWidgetsAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->js[] = 'CommerceWidgets.js';

        parent::init();
    }
}
