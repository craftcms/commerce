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
 * Asset bundle for the Control Panel
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CommerceUiAsset extends AssetBundle
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

        if ($this->getDevServer()) {
            // Development
            $devServer = static::getDevServer();
            $this->js[] = $devServer.'/app.js';
        } else {
            // Production
            $this->js[] = 'js/chunk-vendors.js';
            $this->js[] = 'js/app.js';
            $this->css[] = 'css/chunk-vendors.css';
            $this->css[] = 'css/app.css';
        }

        parent::init();
    }


    /**
     * @return string
     */
    private static function getDevServer(): string
    {
        static $devServer;

        if (!isset($devServer)) {
            $vueCliServer = getenv('COMMERCE_VUE_CLI_SERVER');
            if ($vueCliServer && Craft::$app->config->general->devMode) {
                $devServer = rtrim($vueCliServer, '/') . '/';
            } else {
                $devServer = '';
            }
        }
        return $devServer;
    }
}
