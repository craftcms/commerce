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
use craft\commerce\records\ShippingZone as ShippingZoneRecord;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;

/**
 * Shipping zone model.
 *
 * @property Country[] $countries countries in this Shipping Zone
 * @property array $countryIds all states in this Shipping Zone
 * @property array $countriesNames the names of all countries in this Shipping Zone
 * @property string $cpEditUrl
 * @property bool $isCountryBased
 * @property State[] $states all states in this Shipping Zone
 * @property array $stateIds
 * @property array $statesNames the names of all states in this Shipping Zone
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ShippingAddressZone extends Model implements AddressZoneInterface
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
     * @var string The code to match the zip code.
     * @since 2.2
     */
    public $zipCodeConditionFormula;

    /**
     * @var bool Country based
     */
    private $_isCountryBased = true;

    /**
     * @var Country[]
     */
    private $_countries;

    /**
     * @var State[]
     */
    private $_states;

    // Public Methods
    // =========================================================================

    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/shipping/shippingzones/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    public function getIsCountryBased(): bool
    {
        return $this->_isCountryBased;
    }

    /**
     * @param bool $value
     *
     * @return bool
     */
    public function setIsCountryBased(bool $value): bool
    {
        return $this->_isCountryBased = $value;
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
     * Returns all countries in this Shipping Zone.
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
        $states = [];

        foreach ($this->getStates() as $state) {
            $states[] = $state->id;
        }

        return $states;
    }

    /**
     * Returns all states in this Shipping Zone.
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
     * @return string
     * @since 2.2
     */
    public function getZipCodeConditionFormula(): string
    {
        return (string)$this->zipCodeConditionFormula;
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
     * Returns the names of all countries in this Shipping Zone.
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
     * Returns the names of all states in this Shipping Zone.
     *
     * @return array
     */
    public function getStatesNames(): array
    {
        $states = [];

        /** @var State $state */
        foreach ($this->getStates() as $state) {
            $states[] = $state->getLabel();
        }

        return $states;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], UniqueValidator::class, 'targetClass' => ShippingZoneRecord::class, 'targetAttribute' => ['name']],
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
