<?php

namespace Craft;

/**
 * Class Stripey_OptionTypeRecord
 * @property int id
 * @property string name
 * @property string handle
 *
 * @property Stripey_ProductRecord[] $products
 * @property Stripey_OptionValueRecord[] optionValues
 * @package Craft
 */
class Stripey_OptionTypeRecord extends BaseRecord
{
    public function getTableName()
    {
        return 'stripey_optiontypes';
    }

    public function defineRelations()
    {
        return array(
            'products' => array(static::MANY_MANY, 'Stripey_ProductRecord','stripey_product_optiontypes(productId, optionTypeId)'),
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