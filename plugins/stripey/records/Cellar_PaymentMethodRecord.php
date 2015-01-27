<?php
namespace Craft;

class Cellar_PaymentMethodRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'cellar_payment_methods';
    }

    protected function defineAttributes()
    {
        return array(
            'class' => array(AttributeType::String, 'required' => true),
            'name' => AttributeType::String,
            'settings' => AttributeType::Mixed,
            'enabled' => AttributeType::Bool
        );
    }

    public function defineIndexes()
    {
        return array(
            array('columns' => array('class'), 'unique' => true),
        );
    }
}



