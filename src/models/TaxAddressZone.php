<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\AddressZoneInterface;
use craft\commerce\base\Model;
use craft\commerce\Plugin;
use craft\commerce\records\TaxZone as TaxZoneRecord;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\validators\UniqueValidator;
use DateTime;
use yii\base\InvalidConfigException;

/**
 * Tax zone model.
 *
 * @property string[] $countries countries in this Tax Zone
 * @property array $countriesNames the names of all countries in this Tax Zone
 * @property string $cpEditUrl
 * @property bool $isCountryBased
 * @property string[]|null $administrativeAreas all administrative areas in this Tax Zone
 * @property array $administrativeAreaNames the names of all administrative areas in this Tax Zone
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
     * @var string|null Country when administrative area based
     */
    public ?string $countryCode = null;

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
     * @var string[]|null $_countryCodes
     */
    private ?array $_countries = null;

    /**
     * @var string[]|null $_administrativeAreas
     */
    private ?array $_administrativeAreas = null;


    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('commerce/tax/taxzones/' . $this->id);
    }

    /**
     * If this zone is based on countries only.
     */
    public function getIsCountryBased(): bool
    {
        return $this->_isCountryBased;
    }

    /**
     * @inheritdoc
     */
    public function getCountryCode(): ?string
    {
        if (!$this->getIsCountryBased()) {
            return $this->countryCode;
        }

        return null;
    }


    /**
     * Set if this zone is based on countries only.
     *
     * @param bool $value is the zone country based
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
        return Plugin::getInstance()->getTaxRates()->getTaxRatesByTaxZoneId($this->id);
    }

    /**
     * @return string[]
     */
    public function getCountries(): array
    {
        return $this->_countries ?? [];
    }

    /**
     * Sets countries in this Tax Zone.
     *
     * @param string[]|string $countryCodes
     */
    public function setCountries(array $countryCodes): void
    {
        $countryCodes = Json::decodeIfJson($countryCodes);
        $this->_countries = $countryCodes;
    }

    /**
     * Returns all states in this Tax Zone.
     *
     * @return string[]|null
     */
    public function getAdministrativeAreas(): array
    {
        return $this->_administrativeAreas;
    }

    /**
     * Sets administrative areas in this Tax Zone.
     * Administrative areas are the administrative area or province.
     *
     * @param string[]|string $administrativeAreas
     */
    public function setAdministrativeAreas(mixed $administrativeAreas): void
    {
        $administrativeAreas = Json::decodeIfJson($administrativeAreas);
        $this->_administrativeAreas = $administrativeAreas;
    }

    /**
     * @since 2.2
     */
    public function getZipCodeConditionFormula(): ?string
    {
        return $this->zipCodeConditionFormula;
    }

    /**
     * Returns the names of all countries in this Tax Zone.
     */
    public function getCountriesNames(): array
    {
        $countryNames = [];
        $countries = $this->getCountries();
        foreach ($countries as $country) {
            $countryNames[] = Craft::$app->getAddresses()->getCountryRepository()->get($country)->getName();
        }

        return $countryNames;
    }

    /**
     * Returns the names of all states in this Tax Zone.
     *
     * @throws InvalidConfigException
     */
    public function getAdministrativeAreaNames(): array
    {
        $administrativeAreaNames = [];
        foreach ($this->getAdministrativeAreas() as $administrativeArea) {
            if ($name = Craft::$app->getAddresses()->getSubdivisionRepository()->get($administrativeArea, [$this->countryCode])?->getName()) {
                $administrativeAreaNames[] = $name;
            }
        }

        return $administrativeAreaNames;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['name'], 'required'],
            [['zipCodeConditionFormula'], 'string', 'length' => [1, 65000], 'skipOnEmpty' => true],
            [['name'], UniqueValidator::class, 'targetClass' => TaxZoneRecord::class, 'targetAttribute' => ['name']],
            [
                ['administrativeAreas','countryCode'],
                'required',
                'when' => static function($model) {
                    return !$model->isCountryBased;
                },
            ],
            [
                ['countries'],
                'required',
                'when' => static function($model) {
                    return $model->isCountryBased;
                },
            ],
        ];
    }
}
