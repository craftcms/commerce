<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\Plugin;
use craft\commerce\services\Store;
use craft\elements\Address;
use UnitTester;

/**
 * StoreTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class StoreTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Store
     */
    protected Store $service;

    public function testGetStore(): void
    {
        $store = $this->service->getStore();

        self::assertInstanceOf(Address::class, $store->getLocationAddress());
        self::assertEquals('US', $store->getLocationAddress()->countryCode);
        self::assertEquals('Store', $store->getLocationAddress()->title);
    }

    public function testGetAllEnabledCountriesAsList(): void
    {
        $this->service->getStore()->setCountries(['US', 'AU', 'PH', 'GB']);
        $countriesAsList = $this->service->getStore()->getCountriesList();

        self::assertIsArray($countriesAsList);
        self::assertArrayHasKey('US', $countriesAsList);
        self::assertSame('United States', $countriesAsList['US']);
        self::assertCount(4, $countriesAsList);
    }

    /**
     *
     */
    public function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getStore();
    }
}
