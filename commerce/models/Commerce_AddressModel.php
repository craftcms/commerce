<?php
namespace Craft;

use JsonSerializable;

/**
 * Customer address model.
 *
 * @property int                   $id
 * @property string                $attention
 * @property string                $title
 * @property string                $firstName
 * @property string                $lastName
 * @property string                $address1
 * @property string                $address2
 * @property string                $city
 * @property string                $zipCode
 * @property string                $phone
 * @property string                $alternativePhone
 * @property string                $businessName
 * @property string                $businessTaxId
 * @property string                $businessId
 * @property string                $stateName
 * @property int                   $countryId
 * @property int                   $stateId
 *
 * @property Commerce_CountryModel $country
 * @property Commerce_StateModel   $state
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_AddressModel extends BaseModel
{
    /** @var int|string Either ID of a state or name of state if it's not present in the DB */
    public $stateValue;

    public function getIterator()
    {
        $attributes=$this->getAttributes();
        $attributes['stateValue'] = $this->getStateValue();
        $attributes['stateText'] = $this->getStateText();
        $attributes['countryText'] = $this->getCountryText();
        return new \CMapIterator($attributes);
    }

    /**
     * @return string
     */
    public function getStateValue()
    {
        return $this->stateId ? $this->stateId : ($this->stateName ? $this->stateName : '');
    }

    /**
     * @return string
     */
    public function getStateText()
    {
        return $this->stateName ? $this->stateName : ($this->stateId ? $this->getState()->name : '');
    }

    public function getState()
    {
        return craft()->commerce_states->getStateById($this->stateId);
    }

    /**
     * @return string
     */
    public function getCountryText()
    {
        return $this->countryId ? $this->getCountry()->name : '';
    }

    /*
     * @return Commerce_StateModel|null
     */

    public function getCountry()
    {
        return craft()->commerce_countries->getCountryById($this->countryId);
    }

    /*
     * @return Commerce_CountryModel|null
     */

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/addresses/'.$this->id);
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        $firstName = trim($this->getAttribute('firstName'));
        $lastName = trim($this->getAttribute('lastName'));

        return $firstName.($firstName && $lastName ? ' ' : '').$lastName;
    }

    /**
     * @return void
     */
    public function setAttributes($values)
    {
        if ($values instanceof \CModel)
        {
            $this->stateValue = $values->stateValue;
        }

        if (is_array($values))
        {
            $this->stateValue = isset($values['stateValue']) ? $values['stateValue'] : null;
        }

        parent::setAttributes($values);
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id'               => AttributeType::Number,
            'attention'        => AttributeType::String,
            'title'            => AttributeType::String,
            'firstName'        => AttributeType::String,
            'lastName'         => AttributeType::String,
            'address1'         => AttributeType::String,
            'address2'         => AttributeType::String,
            'city'             => AttributeType::String,
            'zipCode'          => AttributeType::String,
            'phone'            => AttributeType::String,
            'alternativePhone' => AttributeType::String,
            'businessName'     => AttributeType::String,
            'businessTaxId'    => AttributeType::String,
            'businessId'       => AttributeType::String,
            'stateName'        => AttributeType::String,
            'countryId'        => [
                AttributeType::Number,
                'required' => true
            ],
            'stateId'          => AttributeType::Number
        ];
    }
}
