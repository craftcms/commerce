<?php
namespace Craft;

/**
 * Shipping zone state record.
 *
 * @property int taxZoneId
 * @property int stateId
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.records
 * @since     1.0
 */
class Commerce_ShippingZoneStateRecord extends BaseRecord
{

    /**
     * @return string
     */
    public function getTableName()
    {
        return "commerce_shippingzone_states";
    }

    /**
     * @return array
     */
    public function defineIndexes()
    {
        return [
            ['columns' => ['shippingZoneId']],
            ['columns' => ['stateId']],
            ['columns' => ['shippingZoneId', 'stateId'], 'unique' => true],
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
            'shippingZone' => [
                static::BELONGS_TO,
                'Commerce_ShippingZoneRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
            'state'        => [
                static::BELONGS_TO,
                'Commerce_StateRecord',
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE,
                'required' => true
            ],
        ];
    }

}