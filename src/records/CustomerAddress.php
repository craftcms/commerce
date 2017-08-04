<?php

namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Taz zone country
 *
 * @property int $customerId
 * @property int $addressId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class CustomerAddress extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%commerce_customers_addresses}}';
    }
}
