<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\base\AddressZoneInterface;
use craft\commerce\Plugin;
use craft\elements\Address;
use yii\caching\TagDependency;

/**
 * Class AddressZone
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class AddressZone
{
    /**
     * Determine if an address is in a zone.
     */
    public static function addressWithinZone(Address $address, AddressZoneInterface $zone): bool
    {
        if ($zone->getIsCountryBased()) {
            $countryCodes = $zone->getCountries();
            if (!in_array($address->countryCode, $countryCodes, false)) {
                return false;
            }
        }else{
            $countryAndStateMatch = ($address->countryCode == $zone->getCountryCode()) 
                && in_array($address->administrativeArea, $zone->getAdministrativeAreas(), false);
            if (!$countryAndStateMatch) {
                return false;
            }
        }

        // Do we have a condition formula for the zip matching? Blank condition will match all
        if (is_string($zone->getZipCodeConditionFormula()) && $zone->getZipCodeConditionFormula() !== '') {
            $formulasService = Plugin::getInstance()->getFormulas();
            $conditionFormula = $zone->getZipCodeConditionFormula();
            $zipCode = $address->zipCode;

            $cacheKey = get_class($zone) . ':' . $conditionFormula . ':' . $zipCode;

            if (Craft::$app->cache->exists($cacheKey)) {
                $result = Craft::$app->cache->get($cacheKey);
            } else {
                $result = (bool)$formulasService->evaluateCondition($conditionFormula, ['zipCode' => $zipCode], 'Zip Code condition formula matching address');
                Craft::$app->cache->set($cacheKey, $result, null, new TagDependency(['tags' => get_class($zone) . ':' . $zone->id]));
            }

            if (!$result) {
                return false;
            }
        }

        return true;
    }
}