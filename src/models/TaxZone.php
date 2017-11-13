<?php

namespace craft\commerce\models;

use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\helpers\UrlHelper;

/**
 * Tax zone model.
 *
 * @property Country[]       $countries
 * @property array           $countryIds
 * @property array           $stateIds
 * @property array           $countriesNames
 * @property array           $statesNames
 * @property array|TaxRate[] $taxRates
 * @property string          $cpEditUrl
 * @property State[]         $states
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
class TaxZone extends Model
{
    // Properties
    // =========================================================================

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
     * @var Country[] $_countries
     */
    private $_countries;

    /**
     * @var State[] $_states
     */
    private $_states;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/settings/taxzones/'.$this->id);
    }

    /**
     * @return TaxRate[]
     */
    public function getTaxRates(): array
    {
        // TODO: something tells me there's a better way. Load all rates when loading zones?
        $allTaxRates = Plugin::getInstance()->getTaxRates()->getAllTaxRates();
        $taxRates = [];

        /** @var \craft\commerce\models\TaxRate $rate */
        foreach ($allTaxRates as $rate) {
            if ($this->id === $rate->taxZoneId) {
                $taxRates[] = $rate;
            }
        }

        return $taxRates;
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
     * All countries in this Tax Zone.
     *
     * @return array
     */
    public function getCountries(): array
    {
        if (null === $this->_countries) {
            $this->_countries = Plugin::getInstance()->getCountries()->getCountriesByTaxZoneId((int)$this->id);
        }

        return $this->_countries;
    }

    /**
     * Set countries in this Tax Zone.
     *
     * @param Country[] $countries
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
        $stateIds = [];

        foreach ($this->getStates() as $state) {
            $stateIds[] = $state->id;
        }

        return $stateIds;
    }

    /**
     * All states in this Tax Zone.
     *
     * @return array
     */
    public function getStates(): array
    {
        if (null === $this->_states) {
            $this->_states = Plugin::getInstance()->getStates()->getStatesByTaxZoneId((int)$this->id);
        }

        return $this->_states;
    }

    /**
     * Set states in this Tax Zone.
     *
     * @param State[] $states
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
    public function getCountriesNames(): array
    {
        $countryNames = [];
        foreach ($this->getCountries() as $country) {
            $countryNames[] = $country->name;
        }

        return $countryNames;
    }

    /**
     * The names of all states in this Tax Zone.
     *
     * @return array
     */
    public function getStatesNames(): array
    {
        $stateNames = [];
        foreach ($this->getStates() as $state) {
            $stateNames[] = $state->formatName();
        }

        return $stateNames;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['name'], 'required'],
        ];
    }
}
