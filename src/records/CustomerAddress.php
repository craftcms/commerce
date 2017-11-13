<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Taz zone country
 *
 * @property int $customerId
 * @property int $addressId
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class CustomerAddress extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%commerce_customers_addresses}}';
    }
}
