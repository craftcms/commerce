<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\base\AddressZoneInterface;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Tax zone model.
 *
 * @property Country[] $countries countries in this Tax Zone
 * @property array $countryIds
 * @property array $countriesNames the names of all countries in this Tax Zone
 * @property string $cpEditUrl
 * @property bool $isCountryBased
 * @property State[] $states all states in this Tax Zone
 * @property array $stateIds
 * @property array $statesNames the names of all states in this Tax Zone
 * @property array|TaxRate[] $taxRates
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class TaxAddressZone extends Model implements AddressZoneInterface
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
     * @var bool Default
     */
    public $default = false;

    /**
     * @var bool Country based
     */
    private $_isCountryBased = true;

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
        return UrlHelper::cpUrl('commerce/settings/taxzones/' . $this->id);
    }

    /**
     * If this zone is based on countries only.
     *
     * @return bool
     */
    public function getIsCountryBased(): bool
    {
        return $this->_isCountryBased;
    }

    /**
     * Set if this zone is based on countries only.
     *
     * @param bool $value is the zone country based
     * @return void
     */
    public function setIsCountryBased(bool $value)
    {
        $this->_isCountryBased = $value;
    }

    /**
     * @return TaxRate[]
     */
    public function getTaxRates(): array
    {
        return Plugin::getInstance()->getTaxRates()->getTaxRatesForZone($this);
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
     *
     * Returns all countries in this Tax Zone.
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
     * Sets countries in this Tax Zone.
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
     * Returns all states in this Tax Zone.
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
     * Sets states in this Tax Zone.
     *
     * @param State[] $states
     */
    public function setStates($states)
    {
        $this->_states = $states;
    }

    /**
     * Returns the names of all countries in this Tax Zone.
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
     * Returns the names of all states in this Tax Zone.
     *
     * @return array
     */
    public function getStatesNames(): array
    {
        $stateNames = [];
        /** @var State $state */
        foreach ($this->getStates() as $state) {
            $stateNames[] = $state->getLabel();
        }

        return $stateNames;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => TaxZoneRecord::class, 'targetAttribute' => ['name']],
            [
                ['states'], 'required', 'when' => function($model) {
                return !$model->isCountryBased;
            }
            ],
            [
                ['countries'], 'required', 'when' => function($model) {
                return $model->isCountryBased;
            }
            ],
        ];
    }
}
