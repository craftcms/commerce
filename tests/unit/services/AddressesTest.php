<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\helpers\AddressZone as AddressZoneHelper;
use craft\commerce\models\Address;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\services\Addresses;
use craft\db\Query;
use craft\elements\User;
use craftcommercetests\fixtures\AddressesFixture;
use craftcommercetests\fixtures\CustomerFixture;
use craftcommercetests\fixtures\UserAddressesFixture;
use UnitTester;
use yii\base\ExitException;
use yii\db\Exception;

/**
 * AddressesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.4
 */
class AddressesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Addresses $addresses
     */
    protected Addresses $addresses;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'customer' => [
                'class' => CustomerFixture::class,
            ],
            'addresses' => [
                'class' => AddressesFixture::class,
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    public function testAddressWithinZone(): void
    {
        $addressSuccess = $this->addresses->getAddressById(1000);
        $addressFail = $this->addresses->getAddressById(1001);

        /** @var ShippingAddressZone $zoneCountry */
        $zoneCountry = $this->make(ShippingAddressZone::class, [
            'getIsCountryBased' => function() {
                return true;
            },
            'getCountryIds' => function() {
                return ['236'];
            },
        ]);
        self::assertFalse(AddressZoneHelper::addressWithinZone($addressFail, $zoneCountry));

        /** @var ShippingAddressZone $zoneState */
        $zoneState = $this->make(ShippingAddressZone::class, [
            'getIsCountryBased' => function() {
                return false;
            },
        ]);

        $state = new State([
            'id' => '26',
            'name' => 'California',
            'abbreviation' => 'CA',
            'countryId' => '236',
        ]);
        $zoneState->setStates([$state]);
        self::assertFalse(AddressZoneHelper::addressWithinZone($addressFail, $zoneState));

        self::assertTrue(AddressZoneHelper::addressWithinZone($addressSuccess, $zoneCountry));
        self::assertTrue(AddressZoneHelper::addressWithinZone($addressSuccess, $zoneState));

        /** @var ShippingAddressZone $zoneZipCodeCondition */
        $zoneZipCodeCondition = $this->make(ShippingAddressZone::class, [
            'getIsCountryBased' => function() {
                return true;
            },
            'getCountryIds' => function() {
                return ['236'];
            },
        ]);
        $zoneZipCodeCondition->zipCodeConditionFormula = 'zipCode == "12345"';
        self::assertFalse(AddressZoneHelper::addressWithinZone($addressSuccess, $zoneZipCodeCondition));

        $zoneZipCodeCondition->zipCodeConditionFormula = 'zipCode == "88"';
        self::assertTrue(AddressZoneHelper::addressWithinZone($addressSuccess, $zoneZipCodeCondition));
    }

    /**
     *
     */
    protected function _before(): void
    {
        parent::_before();

        $this->addresses = Plugin::getInstance()->getAddresses();
    }
}
