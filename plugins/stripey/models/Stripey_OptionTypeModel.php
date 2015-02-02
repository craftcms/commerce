<?php

namespace Craft;

/**
 * Class Stripey_OptionTypeModel
 * @property int id
 * @property string name
 * @property string handle
 * @package Craft
 */
class Stripey_OptionTypeModel extends BaseModel
{
    function __toString()
    {
        return Craft::t($this->handle);
    }

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/optiontypes/' . $this->id);
    }

    /**
     * @return Stripey_OptionValueModel[]
     */
    public function getOptionValues()
    {
        return craft()->stripey_optionValue->getAllByOptionTypeId($this->id);
    }

    /**
     * [Id => name] list for dropdown
     *
     * @return array
     */
    public function getSelectValues()
    {
        $values = $this->getOptionValues();

        $result = array('' => '');
        foreach($values as $value) {
            $result[$value->id] = $value->displayName;
        }

        return $result;
    }

    protected function defineAttributes()
    {
        return array(
            'id'     => AttributeType::Number,
            'name'   => AttributeType::String,
            'handle' => AttributeType::String
        );
    }

}