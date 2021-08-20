<?php

namespace craft\commerce\helpers;


use craft\commerce\models\Address as AddressModel;
use craft\commerce\models\Country;
use craft\commerce\Plugin;

class Address
{
    /**
     * @param AddressModel $address
     * @param $countryIdParam
     * @return int
     * @throws \yii\base\InvalidConfigException
     */
    public static function getCountryIdByParam(AddressModel $address, $countryIdParam): int
    {
        $countryId = $address->countryId;

        if ($countryId === null) {
            if ($countryIdParam === null) {

                $countryId = self::getDefaultCountry()->id;
            } else {
                $countryId = $countryIdParam;
            }
        }

        return $countryId;
    }

    /**
     * @return Country
     * @throws \yii\base\InvalidConfigException
     */
    public static function getDefaultCountry(): Country
    {
        $storeLocation = Plugin::getInstance()->getAddresses()->getStoreLocationAddress();

        if ($storeLocation->id !== null) {
            return Plugin::getInstance()->getCountries()->getCountryById($storeLocation->id);
        }
        
        return Plugin::getInstance()->getCountries()->getCountryByIso(AddressModel::DEFAULT_COUNTRY_ISO);
    }
}