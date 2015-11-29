<?php
namespace Craft;

/**
 * Country model.
 *
 * @property int $id
 * @property string $name
 * @property string $iso
 * @property bool $stateRequired
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_CountryModel extends BaseModel
{
    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/countries/' . $this->id);
    }

    /**
     * @return string
     */
    function __toString()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id' => AttributeType::Number,
            'name' => AttributeType::String,
            'iso' => AttributeType::String,
            'stateRequired' => AttributeType::Bool,
        ];
    }

}