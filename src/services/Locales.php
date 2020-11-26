<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use yii\base\Component;

/**
 * Locales service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class Locales extends Component
{
    const TYPE_LOCALE_CREATED = 'localeCreated';

    public function setOrderLocale(Order $order, $locale)
    {
        if ($locale === static::TYPE_LOCALE_CREATED) {
            $language = $order->getLanguage();
        } else {
            $site = Craft::$app->getSites()->getSiteById($locale);
       
            $language = $site->language ?? Craft::$app->language;
        }
        
        Craft::$app->language = $language;
        Craft::$app->set('locale', Craft::$app->getI18n()->getLocaleById($language));

        return true;
    }
}
