<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use yii\base\Module;

/**
 * Registers `commerce` as a Yii2 module purely so `yii\base\Module::createController()`
 * can find `craft\commerce\controllers\*` for routes matched by the legacy UrlManager
 * rules in {@see Routes}. `craft\commerce\Plugin` no longer extends `yii\base\Module`
 * (it extends the new Laravel-based `CraftCms\Commerce\Plugin` instead), so without this,
 * `Craft::$app->getModule('commerce')` returns null and every legacy-dispatched Commerce
 * controller 404s, even though the URL rules themselves still match correctly.
 *
 * @since 6.0.0
 */
class LegacyRoutingModule extends Module
{
    public $controllerNamespace = 'craft\commerce\controllers';
}
