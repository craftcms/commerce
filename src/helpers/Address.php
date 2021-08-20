<?php

namespace craft\commerce\helpers;


use craft\commerce\models\Address as AddressModel;
use craft\commerce\Plugin;

class Address
{
    /**
     * @param AddressModel $address
     * @return int
     */
    public static function getCountryIdByParam(AddressModel $address, $countryIdParam): int
    {
        $countryId = $address->countryId;

        if ($countryId === null) {
            if ($countryIdParam === null) {
                $countryId = Plugin::getInstance()->getCountries()->getCountryByIso(AddressModel::DEFAULT_COUNTRY_ISO)->id;

                $storeLocation = Plugin::getInstance()->getAddresses()->getStoreLocationAddress();

                if ($storeLocation->id !== null) {
                    $countryId = $storeLocation->countryId;
                }
            } else {
                $countryId = $countryIdParam;
            }
        }

        return $countryId;
    }
}