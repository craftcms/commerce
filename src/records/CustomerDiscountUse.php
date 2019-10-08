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
 * Customer discount record.
 *
 * @property Customer $customer
 * @property int $customerId
 * @property Discount $discount
 * @property int $discountId
 * @property int $id
 * @property int $uses
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CustomerDiscountUse extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CUSTOMER_DISCOUNTUSES;
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getDiscount(): ActiveQueryInterface
    {
        return $this->hasOne(Discount::class, ['id', 'discountId']);
    }

    /**
     * @return ActiveQueryInterface
     */
    public function getCustomer(): ActiveQueryInterface
    {
        return $this->hasOne(Customer::class, ['id', 'customerId']);
    }
}
