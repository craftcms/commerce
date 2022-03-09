<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Store record.
 *
 * @property int $id
 * @property int $locationAddressId
 * @property array $countries
 * @property array $marketAddressCondition
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::STORES;
    }

    /**
     * Returns the store's location
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getStoreLocation(): ActiveQueryInterface
    {
        return $this->hasOne(\craft\elements\Address::class, ['id' => 'locationAddressId']);
    }
}
