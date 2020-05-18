<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

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

    public function testGetAddressById()
    {
        $this->assertNull($this->addresses->getAddressById(999));

        $address = $this->addresses->getAddressById(1000);
        $this->assertInstanceOf(Address::class, $address);
        $this->assertSame('1640 Riverside Drive', $address->address1);
    }

    public function testGetAddressesByCustomerId()
    {
        $address = $this->addresses->getAddressById(1000);
        $customerAddresses = $this->addresses->getAddressesByCustomerId(88);

        $this->assertIsArray($customerAddresses);
        $this->assertNotEmpty($customerAddresses);
        $this->assertEquals($address, $customerAddresses[0]);
    }

    public function testGetAddressByIdAndCustomerId()
    {
        $customerAddress = $this->addresses->getAddressById(1000);
        $noAddress = $this->addresses->getAddressByIdAndCustomerId(999,88);
        $this->assertNull($noAddress);

        $address = $this->addresses->getAddressByIdAndCustomerId(1000, 88);
        $this->assertEquals($customerAddress, $address);
    }

    public function testGetStoreLocationAddress()
    {
        $storeAddress = $this->addresses->getAddressById(123);
        $address = $this->addresses->getStoreLocationAddress();

        $this->assertIsObject($address);
        $this->assertEquals($storeAddress, $address);
    }

    public function testSaveAddress()
    {
        $address = $this->addresses->getAddressById(1000);
        $address->address2 = 'Great Scott!';

        $saveResult = $this->addresses->saveAddress($address);

        $this->assertTrue($saveResult);
        $this->assertFalse($address->hasErrors());
        $this->assertSame('Great Scott!', $address->address2);

        $address2 = (new Query())
            ->select(['address2'])
            ->from(Table::ADDRESSES)
            ->where(['id' => 1000])
            ->scalar();
        $this->assertSame('Great Scott!', $address2);
    }

    public function testDeleteAddressById()
    {
        $result = $this->addresses->deleteAddressById(1000);

        $this->assertTrue($result);
        $addressExists = (new Query())
            ->from(Table::ADDRESSES)
            ->where(['id' => 1000])
            ->exists();
        $this->assertFalse($addressExists);
    }

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
        $this->assertFalse($this->addresses->addressWithinZone($addressFail, $zoneCountry));

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
        $this->assertFalse($this->addresses->addressWithinZone($addressFail, $zoneState));

        $this->assertTrue($this->addresses->addressWithinZone($addressSuccess, $zoneCountry));
        $this->assertTrue($this->addresses->addressWithinZone($addressSuccess, $zoneState));

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
        $this->assertFalse($this->addresses->addressWithinZone($addressSuccess, $zoneZipCodeCondition));

        $zoneZipCodeCondition->zipCodeConditionFormula = 'zipCode == "88"';
        $this->assertTrue($this->addresses->addressWithinZone($addressSuccess, $zoneZipCodeCondition));
    }

    public function testPurgeOrphanedAddresses()
    {
        $count = (new Query())
            ->from(Table::ADDRESSES)
            ->count();

        $this->assertEquals(3, $count);

        $this->addresses->purgeOrphanedAddresses();

        $newCount = (new Query())
            ->from(Table::ADDRESSES)
            ->count();

        $this->assertNotEquals($count, $newCount);
        $this->assertEquals(2, $newCount);
    }

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

        $this->assertNotEquals(array_keys($addressArray), $keys);
        $this->assertEquals($keysThatShouldExist, $keys);
        $this->assertNotContains('countryText', $keys);
        $this->assertNotContains('stateText', $keys);
        $this->assertNotContains('abbreviationText', $keys);
    }

    protected function _before()
    {
        parent::_before();

        $this->addresses = Plugin::getInstance()->getAddresses();
    }
}
