<?php

namespace Craft;

/**
 * Class Stripey_TaxCategoryModel
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property bool $default
 * @package Craft
 */
class Stripey_TaxCategoryModel extends BaseModel
{
    protected $modelRecord = 'Stripey_TaxCategoryRecord';

    function __toString()
    {
        return Craft::t($this->handle);
    }

    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('stripey/settings/taxcategories/' . $this->id);
    }

    protected function defineAttributes()
    {
        return array(
            'id'            => AttributeType::Number,
            'name'          => AttributeType::String,
            'code'          => AttributeType::String,
            'description'   => AttributeType::String,
            'default'       => AttributeType::Bool,
        );
    }

}