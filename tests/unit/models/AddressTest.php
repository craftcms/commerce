<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Stub;
use Codeception\Test\Unit;
use Craft;
use craft\base\Model;
use craft\commerce\events\ShowAllFormAttributesEvent;
use craft\commerce\helpers\Address as AddressHelper;
use craft\commerce\models\Address;
use craft\commerce\models\Country;
use craft\commerce\models\State;
use craft\commerce\Plugin;
use craft\commerce\services\Addresses;
use craft\commerce\services\Countries;
use craft\commerce\services\States;
use DvK\Vat\Validator;
use Exception;
use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\caching\DummyCache;

/**
 * AddressTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1.8
 */
class AddressTest extends Unit
{
    /**
     *
     */
    public function testGetCpEditUrl(): void
    {
        $address = new Address(['id' => '1001']);
        self::assertSame('http://test.craftcms.test/index.php?p=admin/commerce/addresses/1001', $address->getCpEditUrl());
    }

    /**
     * @dataProvider validateStateDataProvider
     *
     * @param $addressModel
     * @param $hasErrors
     * @param $errors
     * @throws InvalidConfigException
     */
    public function testValidateState($addressModel, $hasErrors, $errors): void
    {
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

        self::assertSame($hasErrors, $addressModel->hasErrors());
        self::assertSame($errors, $addressModel->getErrors());
    }

    /**
     * @dataProvider validateBusinessTaxIdDataProvider
     *
     * @param $businessTaxId
     * @param $hasErrors
     * @param $errors
     * @param $validateBusinessTaxIdAsVatId
     * @throws InvalidConfigException
     */
    public function testValidateBusinessTaxId($businessTaxId, $hasErrors, $errors, $validateBusinessTaxIdAsVatId): void
    {
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
        self::assertSame($hasErrors, $addressModel->hasErrors());
        self::assertSame($errors, $addressModel->getErrors());
    }

    /**
     * @dataProvider getCountryTextDataProvider
     *
     * @param $countryId
     * @param $countryText
     * @throws Exception
     */
    public function testGetCountryText($countryId, $countryText): void
    {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getCountry' => $countryId === 9000 ? new Country(['name' => 'Test Place']) : null,
        ]);

        $address->countryId = $countryId;

        self::assertSame($countryText, $address->getCountryText());
    }

    /**
     * @dataProvider getCountryDataProvider
     *
     * @param $address
     * @param $country
     * @throws InvalidConfigException
     */
    public function testGetCountry($address, $country): void
    {
        $countries = $this->make(Countries::class, [
            'getCountryById' => function($id) {
                if ($id == 9000) {
                    return new Country(['name' => 'Test Place']);
                }

                return null;
            }
        ]);

        Plugin::getInstance()->set('countries', $countries);

        self::assertEquals($country, $address->getCountry());
    }

    /**
     * @dataProvider getCountryIsoDataProvider
     *
     * @param $countryId
     * @param $countryIso
     * @throws Exception
     */
    public function testGetCountryIso($countryId, $countryIso): void
    {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getCountry' => $countryId === 9000 ? new Country(['iso' => 'XX']) : null,
        ]);

        $address->countryId = $countryId;

        self::assertSame($countryIso, $address->getCountryIso());
    }

    /**
     * @dataProvider getStateTextDataProvider
     *
     * @param $stateId
     * @param $stateName
     * @param $stateText
     * @throws Exception
     */
    public function testGetStateText($stateId, $stateName, $stateText): void
    {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getState' => $stateId === 1111 ? new State(['name' => 'Oregon']) : null,
        ]);

        $address->stateId = $stateId;
        $address->stateName = $stateName;

        self::assertSame($stateText, $address->getStateText());
    }

    /**
     * @dataProvider getAbbreviationTextDataProvider
     *
     * @param $stateId
     * @param $abbreviationText
     * @throws Exception
     */
    public function testGetAbbreviationText($stateId, $abbreviationText): void
    {
        /** @var Address $address */
        $address = $this->make(Address::class, [
            'getState' => $stateId === 1111 ? new State(['abbreviation' => 'OR']) : null,
        ]);

        $address->stateId = $stateId;

        self::assertSame($abbreviationText, $address->getAbbreviationText());
    }

    /**
     * @dataProvider getStateDataProvider
     *
     * @param Address $address
     * @param State|null $state
     * @throws InvalidConfigException
     */
    public function testGetState(Address $address, ?State $state): void
    {
        $states = $this->make(States::class, [
            'getStateById' => function($id) {
                if ($id == 1111) {
                    return new State(['name' => 'Test Place']);
                }

                return null;
            }
        ]);

        Plugin::getInstance()->set('states', $states);

        self::assertEquals($state, $address->getState());
    }

    /**
     * @dataProvider getStateValueDataProvider
     *
     * @param Address $address
     * @param $stateValue
     */
    public function testGetStateValue(Address $address, $stateValue): void
    {
        self::assertSame($stateValue, $address->getStateValue());
    }

    /**
     * @dataProvider setStateValueDataProvider
     *
     * @param $value
     * @param $stateId
     * @param $stateName
     * @throws InvalidConfigException
     */
    public function testSetStateValue($value, $stateId, $stateName): void
    {
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

        self::assertSame($stateId, $address->stateId);
        self::assertSame($stateName, $address->stateName);
    }

    /**
     * @dataProvider addressLinesDataProvider
     * @param $address array
     * @param bool $sanitize
     * @param array $expected
     */
    public function testGetAddressLines(array $address, bool $sanitize, array $expected): void
    {
        $addressModel = new Address($address);

        $addressLines = $addressModel->getAddressLines($sanitize);

        self::assertEquals($expected, $addressLines);
    }

    public function testAddressFormat()
    {
        $this->_mockCountryService();

        $expectedFormat = trim("
%givenName %familyName
%organization
%addressLine1
%addressLine2
%locality, %administrativeArea %postalCode
        ");
        $expectedFormat = preg_replace('/[ \t]+/', ' ', preg_replace('/[\r\n]+/', "\n", $expectedFormat));
        $address = new Address();
        $address->countryId = 1;
        $format = $address->getAddressFormat();

        self::assertEquals($expectedFormat, $format->getFormat());
    }
    
    public function testAddressFormAttributesAll()
    {
        Event::on(Address::class, Address::EVENT_SHOW_ALL_FORM_ATTRIBUTES, function (ShowAllFormAttributesEvent $event) {
            $event->all = true;
        });
        
        $this->_mockCountryService();
        
        $address = new Address();
        $address->countryId = 1;
        
        $this->assertEquals($address->attributes(), $address->formAttributes());
    }    
    
    public function testAddressFormAttributesDefault()
    {
        $this->_mockCountryService();
        
        $address = new Address();
        $address->countryId = 1;

        $format = $address->getAddressFormat();
        
        $attributes = array_merge($format->getUsedFields(), $address->getImportantAttributes());
        
        $this->assertEquals($attributes, $address->formAttributes());
    }

    /**
     * @return array
     */
    public function addressLinesDataProvider(): array
    {
        return [
            [['address1' => 'This is address 1'], false, ['address1' => 'This is address 1']],
            [
                [
                    'isStoreLocation' => false,
                    'attention' => '',
                    'title' => 'Dr',
                    'firstName' => 'Emmett',
                    'lastName' => 'Brown',
                    'fullName' => 'Doc Brown',
                    'address1' => '1640 Riverside Drive',
                    'address2' => '',
                    'address3' => '',
                    'city' => 'Hill Valley',
                    'zipCode' => '88',
                    'phone' => '555-555-5555',
                    'alternativePhone' => '',
                    'label' => 'Movies',
                    'businessName' => '',
                    'businessTaxId' => '',
                    'businessId' => '',
                    'countryId' => '236',
                    'stateId' => '26',
                    'notes' => '1.21 gigawatts',
                    'custom1' => 'Einstein',
                    'custom2' => 'Marty',
                    'custom3' => 'George',
                    'custom4' => 'Biff',
                    'isEstimated' => false,
                ],
                false,
                [
                    'name' => 'Dr Emmett Brown',
                    'fullName' => 'Doc Brown',
                    'address1' => '1640 Riverside Drive',
                    'city' => 'Hill Valley',
                    'zipCode' => '88',
                    'phone' => '555-555-5555',
                    'label' => 'Movies',
                    'countryText' => 'United States',
                    'stateText' => 'California',
                    'notes' => '1.21 gigawatts',
                    'custom1' => 'Einstein',
                    'custom2' => 'Marty',
                    'custom3' => 'George',
                    'custom4' => 'Biff',
                ]
            ],
            [['address1' => 'Sanitize <br> this'], true, ['address1' => 'Sanitize &lt;br&gt; this']],
        ];
    }

    /**
     * @return array[]
     */
    public function validateStateDataProvider(): array
    {
        return [
            [new Address(['stateId' => 1, 'countryId' => 9001]), false, []], // Don't check
            [new Address(['stateId' => 1, 'countryId' => 9000]), false, []], // Valid
            [new Address(['stateId' => 2, 'countryId' => 9000]), true, ['stateValue' => ['Country requires a related state selected.']]], // Invalid
        ];
    }

    /**
     * @return array[]
     */
    public function validateBusinessTaxIdDataProvider(): array
    {
        return [
            ['1123', false, [], false], // Don't validate
            ['1123', true, ['businessTaxId' => ['Invalid Business Tax ID.']], true], // validate - invalid
            ['GB000472631', false, [], true], // validate - valid
            ['exists', false, [], true], // validate - valid - already exists
        ];
    }

    /**
     * @return array[]
     */
    public function getCountryTextDataProvider(): array
    {
        return [
            [9000, 'Test Place'],
            [9001, ''],
            [null, ''],
        ];
    }

    /**
     * @return array[]
     */
    public function getCountryDataProvider(): array
    {
        return [
            [new Address(['countryId' => 9000]), new Country(['name' => 'Test Place'])],
            [new Address(['countryId' => 8999]), null],
            [new Address(), null],
        ];
    }

    /**
     * @return array[]
     */
    public function getCountryIsoDataProvider(): array
    {
        return [
            [9000, 'XX'],
            [9001, ''],
            [null, ''],
        ];
    }

    /**
     * @return array[]
     */
    public function getStateTextDataProvider(): array
    {
        return [
            [1111, 'Oregon', 'Oregon'], // Get from state model
            [1111, null, 'Oregon'], // get from state model
            [null, 'Somewhere', 'Somewhere'], // get from stateName prop
            [1112, 'California', ''], // Invalid state id
        ];
    }

    /**
     * @return array[]
     */
    public function getAbbreviationTextDataProvider(): array
    {
        return [
            [1111, 'OR'],
            [1112, ''],
            [null, ''],
        ];
    }

    /**
     * @return array[]
     */
    public function getStateDataProvider(): array
    {
        return [
            [new Address(['stateId' => 1111]), new State(['name' => 'Test Place'])],
            [new Address(['stateId' => 1112]), null],
            [new Address(), null],
        ];
    }

    /**
     * @return array[]
     * @throws Exception
     */
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

    /**
     * @return array
     */
    public function setStateValueDataProvider(): array
    {
        return [
            [null, null, null],
            [false, null, null],
            [1111, 1111, null],
            [1112, null, '1112'],
            ['Somewhere', null, 'Somewhere'],
        ];
    }
    
    private function _mockCountryService()
    {
        $mockCountriesService = $this->make(Countries::class, [
            'getCountryById' => function($id) {
                $country = new Country();
                $country->iso = 'US';

                return $country;
            }
        ]);

        Plugin::getInstance()->set('countries', $mockCountriesService);
    }
}