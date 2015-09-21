<?php

namespace Craft;

/**
 * Class Commerce_OrderStatusRecord
 *
 * @property int                  id
 * @property string               name
 * @property string               handle
 * @property string               color
 * @property bool                 default
 *
 * @property Commerce_EmailRecord[] emails
 * @package Craft
 */
class Commerce_OrderStatusRecord extends BaseRecord
{
	/**
     * @return string
     */
    public function getTableName ()
    {
        return 'commerce_orderstatuses';
    }

    /**
     * @return array
     */
    public function defineRelations ()
    {
        return [
            'emails' => [
                static::MANY_MANY,
                'Commerce_EmailRecord',
                'commerce_orderstatus_emails(orderStatusId, emailId)'
            ],
        ];
    }

	/**
     * @return array
     */
    protected function defineAttributes ()
    {
        return [
            'name'    => [AttributeType::String, 'required' => true],
            'handle'  => [AttributeType::Handle, 'required' => true],
            'color'   => [AttributeType::Enum, 'values' => ['green', 'orange', 'red', 'blue', 'yellow', 'pink', 'purple', 'turquoise', 'light', 'grey', 'black'], 'required' => true, 'default' => 'green'],
            'default' => [
                AttributeType::Bool,
                'default'  => 0,
                'required' => true
            ],
        ];
    }
}