<?php

namespace Craft;

class Stripey_OptionTypeModel extends BaseModel
{
    protected $modelRecord = 'Stripey_OptionTypeRecord';

    function __toString()
    {
        return Craft::t($this->handle);
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/optiontypes/' . $this->id);
    }

    protected function defineAttributes()
    {
        return array(
            'id'            => AttributeType::Number,
            'name'          => AttributeType::String,
            'handle'        => AttributeType::String
        );
    }

}