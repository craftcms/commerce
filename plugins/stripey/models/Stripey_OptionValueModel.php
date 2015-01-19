<?php

namespace Craft;

class Stripey_OptionValueModel extends BaseModel
{
    protected $modelRecord = 'Stripey_OptionValueRecord';

    /** Required for Stripey Editable Table
     * Useful to also lookup editable table order to attribute mapping
     */
    public static function editableColumns()
    {
        return array(
            array('attribute' => 'name',
                  'heading'   => 'Name',
                  'type'      => 'singleline',
                  'width'     => '50%'
            ),
            array('attribute' => 'displayName',
                  'heading'   => 'Display Name',
                  'type'      => 'singleline',
                  'width'     => '50%'
            ),
        );
    }

    function __toString()
    {
        return Craft::t($this->displayName);
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/optiontypes/' . $this->optionTypeId);
    }

    public function getOptionType()
    {
        return craft()->stripey_optionType->getOptionTypeById($this->optionTypeId);
    }

    public function toEditableRow()
    {
        return array($this->name, $this->displayName    );
    }

    protected function defineAttributes()
    {
        return array(
            'id'           => AttributeType::Number,
            'name'         => AttributeType::String,
            'displayName'  => AttributeType::String,
            'position'     => AttributeType::Number,
            'optionTypeId' => AttributeType::Number
        );
    }

}