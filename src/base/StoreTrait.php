<?php

namespace craft\commerce\base;

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
        if (!$store = Plugin::getInstance()->getStores()->getStoreById($this->storeId)) {
            throw new InvalidConfigException('Invalid store ID: ' . $this->storeId);
        }

        return $store;
    }
}
