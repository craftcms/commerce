<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\db\Table;
use craft\commerce\models\Address;
use craft\commerce\models\ShippingAddressZone;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\services\Addresses;
use craft\db\Query;
use craftcommercetests\fixtures\AddressesFixture;
use craftcommercetests\fixtures\CustomersAddressesFixture;
use UnitTester;

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
    protected $tester;

    /**
     * @var Addresses $addresses
     */
    protected $addresses;


    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'addresses' => [
                'class' => AddressesFixture::class,
            ],
            'customers-addresses' => [
                'class' => CustomersAddressesFixture::class,
            ],
        ];
    }

    /**
     *
     */
    public function testGetAddressById()
    {
        self::assertNull($this->addresses->getAddressById(999));

        $address = $this->addresses->getAddressById(1000);
        self::assertInstanceOf(Address::class, $address);
        self::assertSame('1640 Riverside Drive', $address->address1);
    }

    /**
     *
     */
    public function testGetAddressesByCustomerId()
    {
        $address = $this->addresses->getAddressById(1000);
        $customerAddresses = $this->addresses->getAddressesByCustomerId(88);

        self::assertIsArray($customerAddresses);
        self::assertNotEmpty($customerAddresses);
        self::assertEquals($address, $customerAddresses[0]);
    }

    /**
     *
     */
    public function testGetAddressByIdAndCustomerId()
    {
        $customerAddress = $this->addresses->getAddressById(1000);
        $noAddress = $this->addresses->getAddressByIdAndCustomerId(999,88);
        self::assertNull($noAddress);

        $address = $this->addresses->getAddressByIdAndCustomerId(1000, 88);
        self::assertEquals($customerAddress, $address);
    }

    /**
     *
     */
    public function testGetStoreLocationAddress()
    {
        $storeAddress = $this->addresses->getAddressById(123);
        $address = $this->addresses->getStoreLocationAddress();

        self::assertIsObject($address);
        self::assertEquals($storeAddress, $address);
    }

    /**
     * @throws \yii\db\Exception
     */
    public function testSaveAddress()
    {
        $address = $this->addresses->getAddressById(1000);
        $address->address2 = 'Great Scott!';

        $saveResult = $this->addresses->saveAddress($address);

        self::assertTrue($saveResult);
        self::assertFalse($address->hasErrors());
        self::assertSame('Great Scott!', $address->address2);

        $address2 = (new Query())
            ->select(['address2'])
            ->from(Table::ADDRESSES)
            ->where(['id' => 1000])
            ->scalar();
        self::assertSame('Great Scott!', $address2);
    }

    /**
     *
     */
    public function testDeleteAddressById()
    {
        $result = $this->addresses->deleteAddressById(1000);

        self::assertTrue($result);
        $addressExists = (new Query())
            ->from(Table::ADDRESSES)
            ->where(['id' => 1000])
            ->exists();
        self::assertFalse($addressExists);
    }

    /**
     * @throws \Exception
     */
    public function testAddressWithinZone()
    {
        $addressSuccess = $this->addresses->getAddressById(1000);
        $addressFail = $this->addresses->getAddressById(1001);

        /** @var ShippingAddressZone $zoneCountry */
        $zoneCountry = $this->make(ShippingAddressZone::class, [
            'getIsCountryBased' => function() {
                return true;
            },
            'getCountryIds' => function() {
                return ['233'];
            },
        ]);
        self::assertFalse($this->addresses->addressWithinZone($addressFail, $zoneCountry));

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
            'countryId' => '233',
        ]);
        $zoneState->setStates([$state]);
        self::assertFalse($this->addresses->addressWithinZone($addressFail, $zoneState));

        self::assertTrue($this->addresses->addressWithinZone($addressSuccess, $zoneCountry));
        self::assertTrue($this->addresses->addressWithinZone($addressSuccess, $zoneState));

        /** @var ShippingAddressZone $zoneZipCodeCondition */
        $zoneZipCodeCondition = $this->make(ShippingAddressZone::class, [
            'getIsCountryBased' => function() {
                return true;
            },
            'getCountryIds' => function() {
                return ['233'];
            },
        ]);
        $zoneZipCodeCondition->zipCodeConditionFormula = 'zipCode == "12345"';
        self::assertFalse($this->addresses->addressWithinZone($addressSuccess, $zoneZipCodeCondition));

        $zoneZipCodeCondition->zipCodeConditionFormula = 'zipCode == "88"';
        self::assertTrue($this->addresses->addressWithinZone($addressSuccess, $zoneZipCodeCondition));
    }

    /**
     * @throws \yii\base\ExitException
     * @throws \yii\db\Exception
     */
    public function testPurgeOrphanedAddresses()
    {
        $count = (new Query())
            ->from(Table::ADDRESSES)
            ->count();

        self::assertEquals(3, $count);

        $this->addresses->purgeOrphanedAddresses();

        $newCount = (new Query())
            ->from(Table::ADDRESSES)
            ->count();

        self::assertNotEquals($count, $newCount);
        self::assertEquals(2, $newCount);
    }

    /**
     *
     */
    public function testRemoveReadOnlyAttributesFromArray()
    {
        $address = $this->addresses->getAddressById(1000);
        $addressArray = $address->toArray();

        $keysThatShouldExist = [
            'id',
            'isStoreLocation',
            'attention',
            'title',
            'firstName',
            'lastName',
            'fullName',
            'address1',
            'address2',
            'address3',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'label',
            'businessName',
            'businessTaxId',
            'businessId',
            'stateName',
            'countryId',
            'stateId',
            'notes',
            'custom1',
            'custom2',
            'custom3',
            'custom4',
            'isEstimated',
            'stateValue',
        ];
        $keys = array_keys($this->addresses->removeReadOnlyAttributesFromArray($addressArray));

        self::assertNotEquals(array_keys($addressArray), $keys);
        self::assertEquals($keysThatShouldExist, $keys);
        self::assertNotContains('countryText', $keys);
        self::assertNotContains('stateText', $keys);
        self::assertNotContains('abbreviationText', $keys);
    }

    /**
     *
     */
    protected function _before()
    {
        parent::_before();

        $this->addresses = Plugin::getInstance()->getAddresses();
    }
}
