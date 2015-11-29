<?php
namespace Craft;

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
class Commerce_CustomerAddressRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return "commerce_customers_addresses";
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['customerId']],
            ['columns' => ['addressId']],
            ['columns' => ['customerId', 'addressId'], 'unique' => true],
        ];
    }


    /**
     * @inheritDoc BaseRecord::defineRelations()
     *
     * @return array
     */
    public function defineRelations()
    {
        return [
            'customer' => [
                static::BELONGS_TO,
                'Commerce_CustomerRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'address' => [
                static::BELONGS_TO,
                'Commerce_AddressRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

}
