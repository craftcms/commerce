<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Stub;
use Codeception\Test\Unit;
use Craft;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\services\Countries;
use craft\commerce\services\States;
use DvK\Vat\Validator;
use yii\caching\DummyCache;

/**
 * AddressTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class AddressTest extends Unit
{
    public function testGetCpEditUrl() {
        $address = new Address(['id' => '1001']);

        $this->assertSame('http://craftcms.com/index.php?p=admin/commerce/addresses/1001', $address->getCpEditUrl());
    }

    /**
     * @dataProvider validateStateDataProvider
     *
     * @param $addressModel
     * @param $hasErrors
     * @param $errors
     * @throws \yii\base\InvalidConfigException
     */
    public function testValidateState($addressModel, $hasErrors, $errors) {
        $countries = $this->make(Countries::class, [
            'getCountryById' => function($id) {
                return new Country([
                    'id' => $id,
                    'isStateRequired' => $id == 9000,
                ]);
            },
        ]);
        Plugin::getInstance()->set('countries', $countries);

        $states = $this->make(States::class, [
            'getStateById' => function($id) {
                if ($id == 1) {
                    return new State([
                        'countryId' => 9000,
                    ]);
                }

                return new State([
                    'countryId' => 8999,
                ]);
            },
        ]);
        Plugin::getInstance()->set('states', $states);

        $addressModel->validateState(null, null, null);

        $this->assertSame($hasErrors, $addressModel->hasErrors());
        $this->assertSame($errors, $addressModel->getErrors());
    }

    /**
     * @dataProvider validateBusinessTaxIdDataProvider
     *
     * @param $businessTaxId
     * @param $hasErrors
     * @param $errors
     * @param $validateBusinessTaxIdAsVatId
     * @throws \yii\base\InvalidConfigException
     */
    public function testValidateBusinessTaxId($businessTaxId, $hasErrors, $errors, $validateBusinessTaxIdAsVatId) {
        $cache = $this->make(DummyCache::class, [
            'exists' => static function($key) {
                return $key == 'commerce:validVatId:exists';
            }
        ]);

        Craft::$app->set('cache', $cache);

        // Validate the VAT id
        Plugin::getInstance()->getSettings()->validateBusinessTaxIdAsVatId = $validateBusinessTaxIdAsVatId;

        $validator = $this->make(Validator::class, ['validateExistence' => function($val) {
            return $val == 'GB000472631';
        }]);
        $addressModel = Stub::make(new Address, ['businessTaxId' => $businessTaxId, '_vatValidator' => $validator]);

        $addressModel->businessTaxId = $businessTaxId;
        $addressModel->validateBusinessTaxId(null, null, null);

        // No validation to take place
        $this->assertSame($hasErrors, $addressModel->hasErrors());
        $this->assertSame($errors, $addressModel->getErrors());
    }

    /**
     * @dataProvider getCountryTextDataProvider
     *
     * @param $countryId
     * @param $countryText
     * @throws \Exception
     */
    public function testGetCountryText($countryId, $countryText)
    {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getCountry' => $countryId === 9000 ? new Country(['name' => 'Test Place']) : null,
        ]);

        $address->countryId = $countryId;

        $this->assertSame($countryText, $address->getCountryText());
    }

    /**
     * @dataProvider getCountryDataProvider
     *
     * @param $address
     * @param $country
     * @throws \yii\base\InvalidConfigException
     */
    public function testGetCountry($address, $country) {
        $countries = $this->make(Countries::class, [
            'getCountryById' => function($id) {
                if ($id == 9000) {
                    return new Country(['name' => 'Test Place']);
                }

                return null;
            }
        ]);

        Plugin::getInstance()->set('countries', $countries);

        $this->assertEquals($country, $address->getCountry());
    }

    /**
     * @dataProvider getCountryIsoDataProvider
     *
     * @param $countryId
     * @param $countryIso
     * @throws \Exception
     */
    public function testGetCountryIso($countryId, $countryIso) {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getCountry' => $countryId === 9000 ? new Country(['iso' => 'XX']) : null,
        ]);

        $address->countryId = $countryId;

        $this->assertSame($countryIso, $address->getCountryIso());
    }

    /**
     * @dataProvider getStateTextDataProvider
     *
     * @param $stateId
     * @param $stateName
     * @param $stateText
     * @throws \Exception
     */
    public function testGetStateText($stateId, $stateName, $stateText) {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getState' => $stateId === 1111 ? new State(['name' => 'Oregon']) : null,
        ]);

        $address->stateId = $stateId;
        $address->stateName = $stateName;

        $this->assertSame($stateText, $address->getStateText());
    }

    /**
     * @dataProvider getAbbreviationTextDataProvider
     *
     * @param $stateId
     * @param $abbreviationText
     * @throws \Exception
     */
    public function testGetAbbreviationText($stateId, $abbreviationText) {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getState' => $stateId === 1111 ? new State(['abbreviation' => 'OR']) : null,
        ]);

        $address->stateId = $stateId;

        $this->assertSame($abbreviationText, $address->getAbbreviationText());
    }

    /**
     * @dataProvider getStateDataProvider
     *
     * @param $address
     * @param $state
     * @throws \yii\base\InvalidConfigException
     */
    public function testGetState($address, $state) {
        $states = $this->make(States::class, [
            'getStateById' => function($id) {
                if ($id == 1111) {
                    return new State(['name' => 'Test Place']);
                }

                return null;
            }
        ]);

        Plugin::getInstance()->set('states', $states);

        $this->assertEquals($state, $address->getState());
    }

    /**
     * @dataProvider getStateValueDataProvider
     *
     * @param Address $address
     * @param $stateValue
     */
    public function testGetStateValue($address, $stateValue) {
        $this->assertSame($stateValue, $address->getStateValue());
    }

    /**
     * @dataProvider setStateValueDataProvider
     *
     * @param $value
     * @param $stateId
     * @param $stateName
     * @throws \yii\base\InvalidConfigException
     */
    public function testSetStateValue($value, $stateId, $stateName) {
        $states = $this->make(States::class, [
            'getStateById' => function($id) {
                if ($id == 1111) {
                    return new State(['name' => 'Test Place']);
                }

                return null;
            }
        ]);

        Plugin::getInstance()->set('states', $states);

        $address = new Address();
        $address->setStateValue($value);

        $this->assertSame($stateId, $address->stateId);
        $this->assertSame($stateName, $address->stateName);
    }

    public function validateStateDataProvider(): array
    {
        return [
            [new Address(['stateId' => 1, 'countryId' => 9001]), false, []], // Don't check
            [new Address(['stateId' => 1, 'countryId' => 9000]), false, []], // Valid
            [new Address(['stateId' => 2, 'countryId' => 9000]), true, ['stateValue' => ['Country requires a related state selected.']]], // Invalid
        ];
    }

    public function validateBusinessTaxIdDataProvider(): array
    {
        return [
            ['123', false, [], false], // Don't validate
            ['123', true, ['businessTaxId' => ['Invalid Business Tax ID.']], true], // validate - invalid
            ['GB000472631', false, [], true], // validate - valid
            ['exists', false, [], true], // validate - valid - already exists
        ];
    }

    public function getCountryTextDataProvider(): array
    {
        return [
            [9000, 'Test Place'],
            [9001, ''],
            [null, ''],
        ];
    }

    public function getCountryDataProvider(): array
    {
        return [
            [new Address(['countryId' => 9000]), new Country(['name' => 'Test Place'])],
            [new Address(['countryId' => 8999]), null],
            [new Address(), null],
        ];
    }

    public function getCountryIsoDataProvider(): array
    {
        return [
            [9000, 'XX'],
            [9001, ''],
            [null, ''],
        ];
    }

    public function getStateTextDataProvider(): array
    {
        return [
            [1111, 'Oregon', 'Oregon'], // Get from state model
            [1111, null, 'Oregon'], // get from state model
            [null, 'Somewhere', 'Somewhere'], // get from stateName prop
            [1112, 'California', ''], // Invalid state id
        ];
    }

    public function getAbbreviationTextDataProvider(): array
    {
        return [
            [1111, 'OR'],
            [1112, ''],
            [null, ''],
        ];
    }

    public function getStateDataProvider(): array
    {
        return [
            [new Address(['stateId' => 1111]), new State(['name' => 'Test Place'])],
            [new Address(['stateId' => 1112]), null],
            [new Address(), null],
        ];
    }

    public function getStateValueDataProvider(): array
    {
        return [
            [new Address(), ''],
            [new Address(['stateId' => 1111]), 1111],
            [new Address(['stateId' => 1111, 'stateName' => 'Test State']), 1111],
            [new Address(['stateName' => 'Test State']), 'Test State'],
            [Stub::make(new Address, ['_stateValue' => 'Test State']), 'Test State'],
        ];
    }

    public function setStateValueDataProvider(): array
    {
        return [
            [null, null, null],
            [false, null, null],
            [1111, 1111, null],
            [1112, null, 1112],
            ['Somewhere', null, 'Somewhere'],
        ];
    }
}