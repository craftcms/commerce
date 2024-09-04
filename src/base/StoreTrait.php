<?php

namespace craft\commerce\base;

use craft\base\Element;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * Store Trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
trait StoreTrait
{
    /**
     * @var int|null Store ID
     */
    public ?int $storeId = null;

    /**
     * @return Store
     * @throws InvalidConfigException
     */
    public function getStore(): Store
    {
        // If the store ID is not set check to see if the class has a `siteId` property and use that.
        if ($this->storeId === null && !$this instanceof Element) {
            throw new InvalidConfigException('Store ID is required');
        }

        if ($this->storeId === null && $this instanceof Element) {
            $store = Plugin::getInstance()->getStores()->getStoreBySiteId($this->siteId);
            if (!$store) {
                throw new InvalidConfigException('Unable to locate store for site ID: ' . $this->siteId);
            }
            $this->storeId = $store->id;
        }

        if (!$store = Plugin::getInstance()->getStores()->getStoreById($this->storeId)) {
            throw new InvalidConfigException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }
}
