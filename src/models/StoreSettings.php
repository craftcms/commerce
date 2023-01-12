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
use craft\elements\Address as AddressElement;
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
class StoreSettings extends Model
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
    private ?Address $_locationAddress = null;

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
     * @param null|int|int[] $locationAddressId
     */
    public function setLocationAddressId(array|int|null $locationAddressId): void
    {
        if ($locationAddressId === null) {
            $this->_locationAddressId = $this->getLocationAddress()->id;
        }

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
            if ($this->_locationAddressId && $location = Address::findOne($this->_locationAddressId)) {
                $this->_locationAddress = $location;
            } else {
                $storeLocationAddress = new AddressElement();
                $storeLocationAddress->title = 'Store';
                $storeLocationAddress->countryCode = 'US';
                if (Craft::$app->getElements()->saveElement($storeLocationAddress, false)) {
                    $this->_locationAddress = $storeLocationAddress;
                    $this->_locationAddressId = $storeLocationAddress->id;
                } else {
                    throw new \Exception('Could not save store location address');
                }
            }

            return $this->_locationAddress;
        }

        return $this->_locationAddress;
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
        return $this->_marketAddressCondition ?? new ZoneAddressCondition();
    }

    /**
     * @param ZoneAddressCondition|string|array|null $condition
     * @return void
     */
    public function setMarketAddressCondition(ZoneAddressCondition|string|array|null $condition): void
    {
        if ($condition === null) {
            $condition = new ZoneAddressCondition();
        }

        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
        }

        if (!$condition instanceof ZoneAddressCondition) {
            $condition['class'] = ZoneAddressCondition::class;
            /** @var ZoneAddressCondition|mixed $condition */
            $condition = Craft::$app->getConditions()->createCondition($condition);
        }
        $condition->forProjectConfig = false;

        $this->_marketAddressCondition = $condition;
    }
}
