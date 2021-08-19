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
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use DateTime;
use yii\base\InvalidConfigException;

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
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Description
     */
    public ?string $description = null;

    /**
     * @var bool Default
     */
    public bool $default = false;

    /**
     * @var string|null The code to match the zip code.
     * @since 2.2
     */
    public ?string $zipCodeConditionFormula = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateCreated = null;

    /**
     * @var DateTime|null
     * @since 3.4
     */
    public ?DateTime $dateUpdated = null;

    /**
     * @var bool Country based
     */
    private bool $_isCountryBased = true;

    /**
     * @var Country[]|null $_countries
     */
    private ?array $_countries = null;

    /**
     * @var State[]|null $_states
     */
    private ?array $_states = null;


    /**
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/tax/taxzones/' . $this->id);
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
    public function setIsCountryBased(bool $value): void
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
     * @throws InvalidConfigException
     */
    public function getCountries(): array
    {
        if ($this->_countries === null && $this->id) {
            $this->_countries = Plugin::getInstance()->getCountries()->getCountriesByTaxZoneId($this->id);
        }

        return $this->_countries ?? [];
    }

    /**
     * Sets countries in this Tax Zone.
     *
     * @param Country[] $countries
     */
    public function setCountries(array $countries): void
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
     * @throws InvalidConfigException
     */
    public function getStates(): array
    {
        if ($this->_states === null && $this->id) {
            $this->_states = Plugin::getInstance()->getStates()->getStatesByTaxZoneId($this->id);
        }

        return $this->_states ?? [];
    }

    /**
     * Sets states in this Tax Zone.
     *
     * @param State[] $states
     */
    public function setStates(array $states): void
    {
        $this->_states = $states;
    }

    /**
     * @return string|null
     * @since 2.2
     */
    public function getZipCodeConditionFormula(): ?string
    {
        return $this->zipCodeConditionFormula;
    }

    /**
     * Returns the names of all countries in this Tax Zone.
     *
     * @return array
     */
    public function getCountriesNames(): array
    {
        return ArrayHelper::getColumn($this->getCountries(), 'name');
    }

    /**
     * Returns the names of all states in this Tax Zone.
     *
     * @return array
     * @throws InvalidConfigException
     */
    public function getStatesNames(): array
    {
        $stateNames = [];
        foreach ($this->getStates() as $state) {
            $stateNames[] = $state->getLabel();
        }

        return $stateNames;
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name'], 'required'];
        $rules[] = [['zipCodeConditionFormula'], 'string', 'length' => [1, 65000], 'skipOnEmpty' => true];
        $rules[] = [['name'], UniqueValidator::class, 'targetClass' => TaxZoneRecord::class, 'targetAttribute' => ['name']];

        $rules[] = [
            ['states'], 'required', 'when' => static function($model) {
                return !$model->isCountryBased;
            }
        ];
        $rules[] = [
            ['countries'], 'required', 'when' => static function($model) {
                return $model->isCountryBased;
            }
        ];

        return $rules;
    }
}
