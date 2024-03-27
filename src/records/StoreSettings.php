<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\records;

use craft\commerce\db\Table;
use craft\db\ActiveRecord;
use craft\elements\Address;
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
class StoreSettings extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::STORESETTINGS;
    }

    /**
     * Returns the store's location
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getStoreLocation(): ActiveQueryInterface
    {
        return $this->hasOne(Address::class, ['id' => 'locationAddressId']);
    }
}
