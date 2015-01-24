<?php

namespace Craft;

class Stripey_OrderModel extends BaseModel
{

    protected function defineAttributes()
    {
        return array(
            'id'                  => AttributeType::Number,
            'number'              => AttributeType::String,
            'state'               => array(AttributeType::Enum, 'required' => true, 'values' => array('cart', 'address', 'delivery', 'payment', 'confirm', 'complete'), 'default' => 'cart'),
            'itemTotal'           => array(AttributeType::Number, 'decimals' => 4),
            'adjustmentTotal'     => array(AttributeType::Number, 'decimals' => 4),
            'email'               => AttributeType::String,
            'userId'              => AttributeType::Number,
            'completedAt'         => AttributeType::DateTime,
            'billingAddressId'    => AttributeType::Number,
            'shippingAddressId'   => AttributeType::Number,
            'specialInstructions' => AttributeType::String,
            'currency'            => AttributeType::String,
            'lastIp'              => AttributeType::String,
            //TODO add 'shipmentState'
            //TODO add 'paymentState'
        );
    }

    function getTotal()
    {
        return $this->itemTotal + $this->adjustmentTotal;
    }

    public function getOutstandingBalance()
    {
        //TODO $this->total - $this->paymentTotal.
    }

    public function getDisplayItemTotal()
    {
        //TODO pretty version of total with currency and decimal markers.
    }

    public function getDisplayAdjustmentTotal()
    {
        //TODO pretty version of total with currency and decimal markers.
    }

    public function getDisplayTotal()
    {
        //TODO pretty version of total with currency and decimal markers.
    }

    public function getDisplayOutstandingBalance()
    {
        //TODO pretty version of OutstandingBalance with currency and decimal markers.
    }


}