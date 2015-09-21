<?php
namespace Craft;

/**
 * Class Market_OrderStatusEmailRecord
 *
 * @property int                      orderStatusId
 * @property int                      emailId
 *
 * @property Market_OrderStatusRecord orderStatus
 * @property Market_EmailRecord       email
 * @package Craft
 */
class Market_OrderStatusEmailRecord extends BaseRecord
{
    public function getTableName()
    {
        return "market_orderstatus_emails";
    }

    /**
     * @return array
     */
    public function defineRelations()
    {
        return [
            'orderStatus' => [
                static::BELONGS_TO,
                'Market_OrderStatusRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
            'email'       => [
                static::BELONGS_TO,
                'Market_EmailRecord',
                'required' => true,
                'onDelete' => self::CASCADE,
                'onUpdate' => self::CASCADE
            ],
        ];
    }

}