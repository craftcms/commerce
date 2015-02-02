<?php

namespace Craft;


class Stripey_VariantRecord extends BaseRecord
{

    public function getTableName()
    {
        return 'stripey_variants';
    }

    public function defaultScope()
    {
        return array(
            'condition'=>"deletedAt=NULL",
        );
    }


    public function scopes()
    {
        return array(
            'master'=>array(
                'condition'=>'isMaster=1',
            )
        );
    }

    public function defineRelations()
    {
        return array(
            'product'  => array(static::BELONGS_TO, 'Stripey_ProductRecord'),
        );
    }

    protected function defineAttributes()
    {
        return array(
            'isMaster'  => AttributeType::Bool,
            'sku'       => array(AttributeType::String, 'required' => true),
            'price'     => array(AttributeType::Number, 'decimals' => 4, 'required' => true),
            'width'     => array(AttributeType::Number, 'decimals' => 4),
            'height'    => array(AttributeType::Number, 'decimals' => 4),
            'length'    => array(AttributeType::Number, 'decimals' => 4),
            'weight'    => array(AttributeType::Number, 'decimals' => 4),
            'stock'     => array(AttributeType::Number),
            'deletedAt' => array(AttributeType::DateTime,'default' => NULL)
        );
    }

}