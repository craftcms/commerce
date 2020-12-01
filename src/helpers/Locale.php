<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;

/**
 * Locale Helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class Locale
{
    /**
     * Set language of the application
     *
     * @param $toLanguage
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public static function switchAppLanguage($toLanguage)
    {
        Craft::$app->language = $toLanguage;
        Craft::$app->set('locale', Craft::$app->getI18n()->getLocaleById($toLanguage));
    }
}
