<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\web\assets\commerceui;

use Craft;
use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\timepicker\TimepickerAsset;
use craft\web\assets\vue\VueAsset;

/**
 * Commerce Order Edit bundle for the Control Panel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class CommerceOrderAsset extends CommerceUiAsset
{
    /**
     * @inheritdoc
     */
    protected $appJs = 'order.js';

    /**
     * @inheritdoc
     */
    protected $appCss = 'order.css';
}
