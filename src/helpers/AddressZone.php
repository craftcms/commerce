<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

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
            $countryIds = $zone->getCountryIds();

            if (!in_array($address->countryId, $countryIds, false)) {
                return false;
            }
        }

        if (!$zone->getIsCountryBased()) {
            $states = [];
            $countries = [];
            $stateNames = [];
            $stateAbbr = [];

            foreach ($zone->getStates() as $state) {
                $states[] = $state->id;
                $countries[] = $state->countryId;
                $stateNames[] = $state->name;
                $stateAbbr[] = $state->abbreviation;
            }

            $countryAndStateMatch = (in_array($address->countryId, $countries, false) && in_array($address->stateId, $states, false));
            $countryAndStateNameMatch = (in_array($address->countryId, $countries, false) && in_array(strtolower($address->getStateName()), array_map('strtolower', $stateNames), false));
            $countryAndStateAbbrMatch = (in_array($address->countryId, $countries, false) && in_array(strtolower($address->getStateAbbreviation()), array_map('strtolower', $stateAbbr), false));

            if (!$countryAndStateMatch && !$countryAndStateNameMatch && !$countryAndStateAbbrMatch) {
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