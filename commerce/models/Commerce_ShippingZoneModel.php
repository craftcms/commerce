<?php
namespace Craft;

/**
 * Shipping zone model.
 *
 * @property int                     $id
 * @property string                  $name
 * @property string                  $description
 * @property bool                    $countryBased
 * @property bool                    $default
 *
 * @property Commerce_CountryModel[] $countries
 * @property Commerce_StateModel[]   $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class Commerce_ShippingZoneModel extends BaseModel
{
    /** @var Commerce_CountryModel[] $_countries */
    private $_countries;

    /** @var Commerce_CountryModel[] $_states */
    private $_states;

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::getCpUrl('commerce/settings/shippingzones/'.$this->id);
    }

    /**
     * @return array
     */
    public function getCountryIds()
    {
        $countries = [];
        foreach ($this->getCountries() as $country)
        {
            $countries[] = $country->id;
        }

        return $countries;
    }

    /**
     * All countries in this Shipping Zone.
     *
     * @return array
     */
    public function getCountries()
    {
        if (!isset($this->_countries))
        {
            $this->_countries = craft()->commerce_shippingZones->getCountriesByShippingZoneId($this->id);;
        }

        return $this->_countries;
    }

    /**
     * Set countries in this Tax Zone.
     *
     * @param Commerce_CountryModel[] $countries
     *
     * @return null
     */
    public function setCountries($countries)
    {
        $this->_countries = $countries;
    }

    /**
     * @return array
     */
    public function getStateIds()
    {
        $states = [];
        foreach ($this->getStates() as $state)
        {
            $states[] = $state->id;
        }

        return $states;
    }

    /**
     * All states in this Shipping Zone.
     *
     * @return array
     */
    public function getStates()
    {
        if (!isset($this->_states))
        {
            $this->_states = craft()->commerce_shippingZones->getStatesByShippingZoneId($this->id);;
        }

        return $this->_states;
    }

    /**
     * Set states in this shipping Zone.
     *
     * @param Commerce_StateModel[] $states
     *
     * @return null
     */
    public function setStates($states)
    {
        $this->_states = $states;
    }

    /**
     * The names of all countries in this Shipping Zone.
     *
     * @return array
     */
    public function getCountriesNames()
    {
        $countries = [];
        foreach ($this->getCountries() as $country)
        {
            $countries[] = $country->name;
        }

        return $countries;
    }

    /**
     * The names of all states in this Shipping Zone.
     *
     * @return array
     */
    public function getStatesNames()
    {
        $states = [];
        foreach ($this->getStates() as $state)
        {
            $states[] = $state->formatName();
        }

        return $states;
    }

    /**
     * @return array
     */
    protected function defineAttributes()
    {
        return [
            'id'           => AttributeType::Number,
            'name'         => AttributeType::String,
            'description'  => AttributeType::String,
            'countryBased' => [AttributeType::Bool, 'default' => 1],
            'default'      => [AttributeType::Bool, 'default' => 0],
        ];
    }
}
