<?php

namespace Craft;


class Stripey_OptionValueRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'stripey_optionvalues';
    }

    public function defineRelations()
    {
        return array(
//            'productOptionTypes' => array(static::HAS_MANY,'Stripey_ProductOptionTypes','optionTypeId'),
//            'product' => array(static::HAS_MANY,array('user_id'=>'id'),'through'=>'roles'),
            'optionType' => array(static::BELONGS_TO, 'Stripey_OptionTypeRecord', 'required' => true),
        );
    }

    public function defineIndexes()
    {
        return array(
//            array('columns' => array('typeId')),
//            array('columns' => array('availableOn')),
//            array('columns' => array('expiresOn')),
        );
    }

    protected function defineAttributes()
    {
        return array(
            'name'         => AttributeType::String,
            'displayName'  => AttributeType::String,
            'position'     => AttributeType::Number,
            'optionTypeId' => AttributeType::Number
        );
    }

}