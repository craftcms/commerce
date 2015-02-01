<?php
namespace Craft;


class Stripey_OptionValueVariantRecord extends BaseRecord
{

    public function getTableName()
    {
        return "stripey_optionvalues_variants";
    }

    public function defineIndexes()
    {
        return array(
            array('columns' => array('optionValueId')),
            array('columns' => array('variantId')),
            array('columns' => array('optionValueId', 'variantId'), 'unique' => true),
        );
    }

    public function defineRelations()
    {
        return array(
            'optionValue'   => array(static::BELONGS_TO, 'Stripey_OptionValueRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
            'variant'   => array(static::BELONGS_TO, 'Stripey_VariantRecord', 'onDelete' => self::CASCADE, 'onUpdate' => self::CASCADE, 'required' => true),
        );
    }


}