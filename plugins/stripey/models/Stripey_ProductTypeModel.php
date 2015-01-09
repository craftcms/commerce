<?php

namespace Craft;

class Stripey_ProductTypeModel extends BaseModel
{
    protected $modelRecord = 'Stripey_ProductTypeRecord';

    function __toString()
    {
        return Craft::t($this->handle);
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/producttypes/' . $this->id);
    }


    protected function defineAttributes()
    {
        return array(
            'id'            => AttributeType::Number,
            'name'          => AttributeType::String,
            'handle'        => AttributeType::String,
            'fieldLayoutId' => AttributeType::Number
        );
    }


    public function behaviors()
    {
        return array(
            'fieldLayout' => new FieldLayoutBehavior('Stripey_Product'),
        );
    }

}