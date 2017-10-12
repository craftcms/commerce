<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Shipping zone model.
 *
 * @property int       $id
 * @property string    $name
 * @property string    $description
 * @property bool      $countryBased
 * @property bool      $default
 *
 * @property Country[] $countries
 * @property State[]   $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class ShippingZone extends Model
{
    /**
     * @var int ID
     */
    public $id;

    /**
     * @var string Name
     */
    public $name;

    /**
     * @var string Description
     */
    public $description;

    /**
     * @var bool Country based
     */
    public $countryBased = true;

    /**
     * @var bool Default
     */
    public $default = false;

    /**
     * @var Country[]
     */
    private $_countries;

    /**
     * @var State[]
     */
    private $_states;

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/shippingzones/'.$this->id);
    }

    /**
     * @return array
     */
    public function getCountryIds(): array
    {
        $countries = [];
        foreach ($this->getCountries() as $country) {
            $countries[] = $country->id;
        }

        return $countries;
    }

    /**
     * All countries in this Shipping Zone.
     *
     * @return array
     */
    public function getCountries(): array
    {
        if (null === $this->_countries) {
            $this->_countries = Plugin::getInstance()->getCountries()->getCountriesByShippingZoneId((int)$this->id);
        }

        return $this->_countries;
    }

    /**
     * Set countries in this Tax Zone.
     *
     * @param \craft\commerce\models\Country[] $countries
     */
    public function setCountries($countries)
    {
        $this->_countries = $countries;
    }

    /**
     * @return array
     */
    public function getStateIds(): array
    {
        $states = [];
        foreach ($this->getStates() as $state) {
            $states[] = $state->id;
        }

        return $states;
    }

    /**
     * All states in this Shipping Zone.
     *
     * @return array
     */
    public function getStates(): array
    {
        if ($this->_states === null) {
            $this->_states = Plugin::getInstance()->getStates()->getStatesByShippingZoneId($this->id);
        }

        return $this->_states;
    }

    /**
     * Set states in this shipping Zone.
     *
     * @param State[] $states
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
    public function getCountriesNames(): array
    {
        $countries = [];
        foreach ($this->getCountries() as $country) {
            $countries[] = $country->name;
        }

        return $countries;
    }

    /**
     * The names of all states in this Shipping Zone.
     *
     * @return array
     */
    public function getStatesNames(): array
    {
        $states = [];
        foreach ($this->getStates() as $state) {
            $states[] = $state->formatName();
        }

        return $states;
    }
}
