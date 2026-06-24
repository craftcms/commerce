<?php

namespace craft\commerce\base;

use craft\commerce\records\Store;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Store Record Trait
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.0.0
 */
trait StoreRecordTrait
{
    /**
     * @return ActiveQueryInterface
     */
    public function getStore(): ActiveQueryInterface
    {
        /** @var ActiveRecord $this */
        return $this->hasOne(Store::class, ['id' => 'storeId']);
    }
}
