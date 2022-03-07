<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\commerceui;

use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\timepicker\TimepickerAsset;
use craft\web\assets\vue\VueAsset;

/**
 * Asset bundle for the Control Panel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
abstract class CommerceUiAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist/';

        $this->depends = [
            CommerceCpAsset::class,
            CpAsset::class,
            VueAsset::class,
            TimepickerAsset::class,
        ];

        parent::init();
    }
}
