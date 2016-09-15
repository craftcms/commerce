<?php
namespace Craft;

use JsonSerializable;

/**
 * State model.
 *
 * @property int                    $id
 * @property string                 $name
 * @property string                 $abbreviation
 * @property int                    $countryId
 *
 * @property Commerce_CountryRecord $country
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_StateModel extends BaseModel implements JsonSerializable
{
    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/states/'.$this->id);
    }

    /**
     * @return string
     */
    function __toString()
    {
        return (string)$this->name;
    }

    /**
     * @return array
     */
    function jsonSerialize()
    {
        $data = [];
        $data['id'] = $this->getAttribute('id');
        $data['name'] = $this->getAttribute('name');
        $data['abbreviation'] = $this->getAttribute('abbreviation');
        $data['countryId'] = $this->getAttribute('countryId');

        return $data;
    }

    /**
     * @return Commerce_CountryModel|null
     */
    public function getCountry()
    {
        return craft()->commerce_countries->getCountryById($this->countryId);
    }

    /**
     * @return string
     */
    public function formatName()
    {
        return $this->name.' ('.$this->country->name.')';
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id'           => AttributeType::Number,
            'name'         => AttributeType::String,
            'abbreviation' => AttributeType::String,
            'countryId'    => AttributeType::Number,
        ];
    }
}