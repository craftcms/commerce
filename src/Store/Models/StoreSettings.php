<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Store\Models;

use craft\commerce\elements\conditions\addresses\ZoneAddressCondition;
use craft\commerce\records\StoreSettings as StoreSettingsRecord;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Component\Component;
use CraftCms\Cms\Support\Facades\Addresses;
use CraftCms\Cms\Support\Facades\Conditions;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Json;
use Illuminate\Support\Arr;

class StoreSettings extends Component
{
    public int $id;

    private ?int $_locationAddressId = null;

    private ?Address $_locationAddress = null;

    private array $_countries = [];

    private ?ZoneAddressCondition $_marketAddressCondition = null;

    public function setLocationAddressId(array|int|null $locationAddressId): void
    {
        if ($locationAddressId === null) {
            $this->_locationAddressId = $this->getLocationAddress()?->id;
            return;
        }

        if (is_array($locationAddressId)) {
            $this->_locationAddressId = Arr::first($locationAddressId) ?: null;
        } else {
            $this->_locationAddressId = $locationAddressId;
        }
    }

    public function getLocationAddressId(): ?int
    {
        return $this->_locationAddressId;
    }

    public function getLocationAddress(): ?Address
    {
        if (!isset($this->_locationAddress)) {
            if ($this->_locationAddressId) {
                /** @var Address|null $location */
                $location = Elements::getElementById($this->_locationAddressId, Address::class);
                if ($location) {
                    $this->_locationAddress = $location;
                    return $this->_locationAddress;
                }
            }

            $storeLocationAddress = new Address();
            $storeLocationAddress->title = 'Store';
            $storeLocationAddress->countryCode = 'US';
            if (Elements::saveElement($storeLocationAddress, false)) {
                $this->setLocationAddress($storeLocationAddress);
                /** @phpstan-ignore-next-line */
                StoreSettingsRecord::updateAll(['locationAddressId' => $this->_locationAddressId], ['id' => $this->id]);
            } else {
                throw new \Exception('Could not save store location address');
            }
        }

        return $this->_locationAddress;
    }

    public function setLocationAddress(?Address $locationAddress = null): void
    {
        $this->_locationAddress = $locationAddress;
        $this->setLocationAddressId($locationAddress?->id);
    }

    public function getCountries(): array
    {
        return $this->_countries;
    }

    public function setCountries(mixed $countries): void
    {
        $countries ??= [];
        $countries = Json::decodeIfJson($countries) ?? [];

        if (!is_array($countries)) {
            throw new \InvalidArgumentException('Countries must be an array.');
        }

        $this->_countries = $countries;
    }

    public function getCountriesList(): array
    {
        $all = Addresses::getCountryRepository()->getList(app()->getLocale());
        return array_filter($all, fn($fieldHandle) => in_array($fieldHandle, $this->getCountries(), true), ARRAY_FILTER_USE_KEY);
    }

    public function getAdministrativeAreasListByCountryCode(): array
    {
        if (empty($this->_countries)) {
            return [];
        }

        $administrativeAreas = [];
        foreach ($this->_countries as $countryCode) {
            $administrativeAreas[$countryCode] = Addresses::getSubdivisionRepository()->getList([$countryCode]);
        }

        return $administrativeAreas;
    }

    public function getMarketAddressCondition(): ZoneAddressCondition
    {
        if ($this->_marketAddressCondition !== null) {
            return $this->_marketAddressCondition;
        }

        /** @var ZoneAddressCondition $condition */
        $condition = Conditions::createCondition(ZoneAddressCondition::class);
        return $condition;
    }

    public function setMarketAddressCondition(ZoneAddressCondition|string|array|null $condition): void
    {
        if (is_string($condition)) {
            $condition = Json::decodeIfJson($condition);
            $condition = Conditions::createCondition($condition);
        }

        if (is_array($condition)) {
            $condition = Conditions::createCondition($condition);
        }

        if ($condition === null) {
            $condition = Conditions::createCondition(ZoneAddressCondition::class);
        }

        $condition->forProjectConfig = false;

        /** @var ZoneAddressCondition $condition */
        $this->_marketAddressCondition = $condition;
    }
}
