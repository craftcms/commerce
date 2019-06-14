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
class CommerceUiAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

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

//        $this->js[] = 'js/chunk-vendors.js';
//        $this->js[] = 'js/app.js';
//
//        $this->css[] = 'css/chunk-vendors.css';
//        $this->css[] = 'css/app.css';

        $this->js[] = 'http://localhost:8080/app.js';

        parent::init();
    }
}
