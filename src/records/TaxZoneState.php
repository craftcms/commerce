<?php
namespace craft\commerce\records;

use craft\db\ActiveRecord;

/**
 * Tax zone state record.
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
class TaxZoneState extends ActiveRecord
{

    /**
     * @return string
     */
    public static function tableName()
    {
        return "commerce_taxzone_states";
    }

//    /**
//     * @return array
//     */
//    public function defineIndexes()
//    {
//        return [
//            ['columns' => ['taxZoneId']],
//            ['columns' => ['stateId']],
//            ['columns' => ['taxZoneId', 'stateId'], 'unique' => true],
//        ];
//    }
//
//
//    /**
//     * @inheritDoc BaseRecord::defineRelations()
//     *
//     * @return array
//     */
//    public function defineRelations()
//    {
//        return [
//            'taxZone' => [
//                static::BELONGS_TO,
//                'TaxZone',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//            'state' => [
//                static::BELONGS_TO,
//                'State',
//                'onDelete' => self::CASCADE,
//                'onUpdate' => self::CASCADE,
//                'required' => true
//            ],
//        ];
//    }

}