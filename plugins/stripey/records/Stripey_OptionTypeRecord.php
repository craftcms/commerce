<?php

namespace Craft;


class Stripey_OptionTypeRecord extends BaseRecord
{


    public function getTableName()
    {
        return 'stripey_optiontypes';
    }

    public function rules()
    {
        return array_merge(parent::rules(),array(

        ));
    }

    public function defineRelations()
    {
        return array(
            'product' => array(static::MANY_MANY, 'Stripey_ProductRecord','stripey_product_optiontypes(productId, optionTypeId)'),
            'optionValues' => array(static::HAS_MANY,'Stripey_OptionValueRecord','optionTypeId'),
        );
    }

    public function beforeDelete()
    {
        Stripey_OptionValueRecord::model()->deleteAllByAttributes(array('optionTypeId' => $this->id));
        return parent::beforeDelete();
    }

    protected function defineAttributes()
    {
        return array(
            'name'   => array(AttributeType::Name, 'required' => true),
            'handle' => array(AttributeType::Handle, 'required' => true)
        );
    }

}