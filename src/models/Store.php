<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\elements\Address;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use yii\base\InvalidConfigException;

/**
 * Store record.
 *
 * @property int $id
 * @property int $locationAddressId
 * @property array $countries
 * @property Address|null $locationAddress
 * @property-read array $countriesList
 * @property-read array $administrativeAreasListByCountryCode
 * @property array|ZoneAddressCondition $marketAddressCondition
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class Store extends Model
{
    /**
     * @var int
     */
    public int $id;

    /**
     * @var int|null
     */
    private ?int $_locationAddressId = null;

    /**
     * @var Address|false|null
     */
    private Address|bool|null $_locationAddress = null;

    /**
     * @var array
     */
    private array $_countries = [];

    /**
     * @var ?ZoneAddressCondition
     */
    private ?ZoneAddressCondition $_marketAddressCondition;

    /**
     * Sets the store location address ID.
     *
     * @param int|int[] $locationAddressId
     */
    public function setLocationAddressId(array|int $locationAddressId): void
    {
        if (is_array($locationAddressId)) {
            $this->_locationAddressId = ArrayHelper::firstValue($locationAddressId) ?: null;
        } else {
            $this->_locationAddressId = $locationAddressId;
        }
    }

    /**
     * Returns the store location address ID.
     *
     * @return int|null
     */
    public function getLocationAddressId(): ?int
    {
        return $this->_locationAddressId;
    }

    /**
     * @return ?Address
     */
    public function getLocationAddress(): ?Address
    {
        if (!isset($this->_locationAddress)) {
            if (!$this->getLocationAddressId()) {
                return null;
            }

            if (($this->_locationAddress = Address::findOne($this->getLocationAddressId())) === null) {
                $this->_locationAddress = false;
            }
        }

        return $this->_locationAddress ?: null;
    }

    /**
     * Sets the store's location address.
     *
     * @param Address|null $locationAddress
     */
    public function setLocationAddress(?Address $locationAddress = null): void
    {
        $this->_locationAddress = $locationAddress;
        $this->setLocationAddressId($locationAddress?->id);
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $fields =  parent::extraFields();
        $fields[] = 'locationAddress';

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function attributes(): array
    {
        $names = parent::attributes();
        $names[] = 'locationAddressId';
        $names[] = 'countries';
        $names[] = 'marketAddressCondition';
        return $names;
    }

    /**
     * @inheritdoc
     */
    public function safeAttributes(): array
    {
        return [
            'id',
            'locationAddressId',
            'countries',
            'marketAddressCondition',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['countries'], 'required'];
        return $rules;
    }

    /**
     * @return string[] $countries
     */
    public function getCountries(): array
    {
        return $this->_countries ?? [];
    }

    /**
     * @param mixed $countries
     * @return void
     * @throws InvalidConfigException
     */
    public function setCountries(mixed $countries): void
    {
        $countries = $countries ?? [];
        $countries = Json::decodeIfJson($countries);

        if (!is_array($countries)) {
            throw new InvalidConfigException('Countries must be an array.');
        }

        $this->_countries = $countries;
    }

    /**
     * @return array
     */
    public function getCountriesList(): array
    {
        $all = Craft::$app->getAddresses()->getCountryRepository()->getList(Craft::$app->language);
        return array_filter($all, function($fieldHandle) {
            return in_array($fieldHandle, $this->getCountries(), true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array
     */
    public function getAdministrativeAreasListByCountryCode(): array
    {
        if (empty($this->_countries)) {
            return [];
        }

        $administrativeAreas = [];
        foreach ($this->_countries as $countryCode) {
            $administrativeAreas[$countryCode] = Craft::$app->getAddresses()->getSubdivisionRepository()->getList([$countryCode]);
        }

        return $administrativeAreas;
    }

    /**
     * @return ZoneAddressCondition
     */
    public function getMarketAddressCondition(): ZoneAddressCondition
    {
        return $this->_marketAddressCondition ?? new ZoneAddressCondition(Address::class);
    }

    /**
     * @param ZoneAddressCondition|string|array|null $condition
     * @return void
     */
    public function setMarketAddressCondition(ZoneAddressCondition|string|array|null $condition): void
    {
        if ($condition === null) {
            $condition = new ZoneAddressCondition(Address::class);
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ZoneAddressCondition) {
            $condition['class'] = ZoneAddressCondition::class;

            // @TODO remove at next breaking change. Fix for misconfiguration during 3.x -> 4.x migration
            $condition['elementType'] = Address::class;

            /** @var ZoneAddressCondition|mixed $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_marketAddressCondition = $condition;
    }
}
