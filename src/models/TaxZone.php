<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\helpers\UrlHelper;

/**
 * Tax zone model.
 *
 * @property \craft\commerce\models\Country[] $countries
 * @property \craft\commerce\models\State[]   $states
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.models
 * @since     1.0
 */
class TaxZone extends Model
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
     * @var mixed Date Created
     */
    public $dateCreated;

    /**
     * @var mixed Date Updated
     */
    public $dateUpdated;

    /**
     * @var string Unique ID
     */
    public $uid;

    /** @var \craft\commerce\models\Country[] $_countries */
    private $_countries;

    /** @var \craft\commerce\models\Country[] $_states */
    private $_states;

    /**
     * @return string
     */
    public function getCpEditUrl()
    {
        return UrlHelper::cpUrl('commerce/settings/taxzones/'.$this->id);
    }

    /**
     * @return \craft\commerce\models\TaxRate[]
     */
    public function getTaxRates()
    {
        $allTaxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRates();
        $taxRates = [];
        /** @var \craft\commerce\models\TaxRate $rate */
        foreach ($allTaxRates as $rate) {
            if ($this->id == $rate->taxZoneId) {
                $taxRates[] = $rate;
            }
        }

        return $taxRates;
    }

    /**
     * @return array
     */
    public function getCountryIds()
    {
        $countries = [];
        foreach ($this->getCountries() as $country) {
            $countries[] = $country->id;
        }

        return $countries;
    }

    /**
     * All countries in this Tax Zone.
     *
     * @return array
     */
    public function getCountries()
    {
        if (null === $this->_countries) {
            $this->_countries = Plugin::getInstance()->getTaxZones()->getCountriesByTaxZoneId($this->id);;
        }

        return $this->_countries;
    }

    /**
     * Set countries in this Tax Zone.
     *
     * @param \craft\commerce\models\Country[] $countries
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
        foreach ($this->getStates() as $state) {
            $states[] = $state->id;
        }

        return $states;
    }

    /**
     * All states in this Tax Zone.
     *
     * @return array
     */
    public function getStates()
    {
        if (null === $this->_states) {
            $this->_states = Plugin::getInstance()->getTaxZones()->getStatesByTaxZoneId($this->id);
        }

        return $this->_states;
    }

    /**
     * Set states in this Tax Zone.
     *
     * @param \craft\commerce\models\State[] $states
     *
     * @return null
     */
    public function setStates($states)
    {
        $this->_states = $states;
    }

    /**
     * The names of all countries in this Tax Zone.
     *
     * @return array
     */
    public function getCountriesNames()
    {
        $countries = [];
        foreach ($this->getCountries() as $country) {
            $countries[] = $country->name;
        }

        return $countries;
    }

    /**
     * The names of all states in this Tax Zone.
     *
     * @return array
     */
    public function getStatesNames()
    {
        $states = [];
        foreach ($this->getStates() as $state) {
            $states[] = $state->formatName();
        }

        return $states;
    }
}
