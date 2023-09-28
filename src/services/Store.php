<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\Store as StoreModel;
use craft\commerce\Plugin;
use craft\errors\SiteNotFoundException;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * Stores service.
 *
 * @property-read StoreModel $store
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends Component
{
    /**
     * Returns the current store.
     *
     * @return StoreModel
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @deprecated in 5.0.0. Use [[Stores::getCurrentStore()]] instead.
     */
    public function getStore(): StoreModel
    {
        Craft::$app->getDeprecator()->log(__METHOD__, 'craft\commerce\services\Store::getStore() has been deprecated. Use craft\commerce\services\Stores::getCurrentStore() instead.');
        return Plugin::getInstance()->getStores()->getCurrentStore();
    }
}