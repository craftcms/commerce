<?php

namespace Craft;

/**
 * Class Market_TaxCategoryModel
 *
 * @property int    $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property bool   $default
 * @package Craft
 */
class Market_TaxCategoryModel extends BaseModel
{
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('market/settings/taxcategories/' . $this->id);
    }

    protected function defineAttributes()
    {
        return [
            'id'          => AttributeType::Number,
            'name'        => AttributeType::String,
            'code'        => AttributeType::String,
            'description' => AttributeType::String,
            'default'     => AttributeType::Bool,
        ];
    }

}